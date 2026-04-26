<?php

use App\Logging\EmailLogHandler;
use App\Mail\ErrorOccurred;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Monolog\Level;
use Monolog\LogRecord;

function makeLogRecord(Level $level = Level::Error, string $message = 'Test error', array $context = []): LogRecord
{
    return new LogRecord(
        datetime: new \DateTimeImmutable,
        channel: 'test',
        level: $level,
        message: $message,
        context: $context,
    );
}

describe('EmailLogHandler', function () {
    beforeEach(function () {
        Mail::fake();
        Cache::flush();
        config()->set('klog.maintainer_email', null);
    });

    describe('sending error emails', function () {
        it('sends an email when an error is logged', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord());

            Mail::assertSent(ErrorOccurred::class, function (ErrorOccurred $mail) {
                return $mail->hasTo('admin@example.com')
                    && $mail->errorMessage === 'Test error'
                    && $mail->errorLevel === 'Error';
            });
        });

        it('does not send for warning-level logs', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord(Level::Warning));

            Mail::assertNothingSent();
        });

        it('does not send for info-level logs', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord(Level::Info));

            Mail::assertNothingSent();
        });

        it('sends for critical-level logs', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord(Level::Critical, 'Critical failure'));

            Mail::assertSent(ErrorOccurred::class);
        });

        it('sends for emergency-level logs', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord(Level::Emergency, 'Emergency failure'));

            Mail::assertSent(ErrorOccurred::class);
        });

        it('includes stack trace when exception is in context', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');
            $exception = new \RuntimeException('Boom');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord(message: 'Error with trace', context: ['exception' => $exception]));

            Mail::assertSent(ErrorOccurred::class, function (ErrorOccurred $mail) {
                return $mail->stackTrace !== null;
            });
        });

        it('sends email to MAINTAINER_EMAIL when configured', function () {
            config()->set('klog.maintainer_email', 'maintainer@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord());

            Mail::assertSent(ErrorOccurred::class, function (ErrorOccurred $mail) {
                return $mail->hasTo('maintainer@example.com');
            });
        });
    });

    describe('recipient fallback', function () {
        it('falls back to the first user when no maintainer configured', function () {
            User::factory()->create(['email' => 'user1@example.com']);
            User::factory()->create(['email' => 'user2@example.com']);

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord());

            Mail::assertSent(ErrorOccurred::class, function (ErrorOccurred $mail) {
                return $mail->hasTo('user1@example.com');
            });
        });

        it('saves the successful recipient to app_settings under the auto-discovery key', function () {
            User::factory()->create(['email' => 'user1@example.com']);

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord());

            // Stored under the auto-discovery key (not the admin-configured one)
            // so it doesn't override an env-set MAINTAINER_EMAIL.
            expect(AppSetting::getValue('maintainer_email_autodiscovered'))->toBe('user1@example.com');
            expect(AppSetting::getValue('maintainer_email'))->toBeNull();
        });

        it('uses stored setting on subsequent errors', function () {
            AppSetting::setValue('maintainer_email', 'stored@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord());

            Mail::assertSent(ErrorOccurred::class, function (ErrorOccurred $mail) {
                return $mail->hasTo('stored@example.com');
            });
        });

        it('does not crash when no recipients are available', function () {
            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord());

            Mail::assertNothingSent();
        });

        it('does not save maintainer when using env var', function () {
            config()->set('klog.maintainer_email', 'env@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord());

            expect(AppSetting::getValue('maintainer_email'))->toBeNull();
        });
    });

    describe('rate limiting', function () {
        it('sends only one email per error within the throttle window', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord(message: 'Duplicate error'));
            $handler->handle(makeLogRecord(message: 'Duplicate error'));
            $handler->handle(makeLogRecord(message: 'Duplicate error'));

            Mail::assertSentCount(1);
        });

        it('sends separate emails for different error messages', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            $handler = new EmailLogHandler;
            $handler->handle(makeLogRecord(message: 'Error A'));
            $handler->handle(makeLogRecord(message: 'Error B'));

            Mail::assertSentCount(2);
        });
    });

    describe('infinite loop prevention', function () {
        it('does not recurse when sending email triggers logging', function () {
            config()->set('klog.maintainer_email', 'admin@example.com');

            // Simulate: the Mail facade's send triggers another error log
            // by using a fake that calls the handler recursively.
            $handler = new EmailLogHandler;
            $callCount = 0;

            Mail::shouldReceive('to')->andReturnUsing(function () use ($handler, &$callCount) {
                $callCount++;

                // Simulate the mail transport logging an error during send
                $handler->handle(makeLogRecord(message: 'Recursive error from mail'));

                // Return a mock that accepts send()
                $mock = Mockery::mock();
                $mock->shouldReceive('send')->once();

                return $mock;
            });

            $handler->handle(makeLogRecord(message: 'Original error'));

            // The handler should have been called twice (original + recursive)
            // but only one Mail::to() call should happen (the recursive one is blocked)
            expect($callCount)->toBe(1);
        });
    });
});
