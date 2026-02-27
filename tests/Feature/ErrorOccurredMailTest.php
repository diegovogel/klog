<?php

use App\Mail\ErrorOccurred;

describe('ErrorOccurred mailable', function () {
    it('renders with all fields', function () {
        $mailable = new ErrorOccurred(
            errorMessage: 'Something went wrong',
            errorLevel: 'ERROR',
            occurredAt: '2026-01-15 10:30:00 UTC',
            stackTrace: '#0 /app/test.php(10): doSomething()',
        );

        $html = $mailable->render();

        expect($html)
            ->toContain('Something went wrong')
            ->toContain('ERROR')
            ->toContain('2026-01-15 10:30:00 UTC')
            ->toContain('#0 /app/test.php(10): doSomething()')
            ->toContain('Stack Trace');
    });

    it('renders without stack trace', function () {
        $mailable = new ErrorOccurred(
            errorMessage: 'A simple error',
            errorLevel: 'ERROR',
            occurredAt: '2026-01-15 10:30:00 UTC',
        );

        $html = $mailable->render();

        expect($html)
            ->toContain('A simple error')
            ->not->toContain('Stack Trace');
    });

    it('includes app name in subject', function () {
        config()->set('app.name', 'TestApp');

        $mailable = new ErrorOccurred(
            errorMessage: 'Test error',
            errorLevel: 'ERROR',
            occurredAt: '2026-01-15 10:30:00 UTC',
        );

        expect($mailable->envelope()->subject)->toContain('[TestApp]');
    });

    it('truncates long error messages in subject', function () {
        $longMessage = str_repeat('x', 200);

        $mailable = new ErrorOccurred(
            errorMessage: $longMessage,
            errorLevel: 'ERROR',
            occurredAt: '2026-01-15 10:30:00 UTC',
        );

        expect(strlen($mailable->envelope()->subject))->toBeLessThan(120);
    });

    it('includes forwarding notice for unknown recipients', function () {
        config()->set('app.name', 'MyKlog');

        $mailable = new ErrorOccurred(
            errorMessage: 'Test error',
            errorLevel: 'ERROR',
            occurredAt: '2026-01-15 10:30:00 UTC',
        );

        $html = $mailable->render();

        expect($html)
            ->toContain('If you are not the owner of MyKlog')
            ->toContain('please forward this message to them')
            ->toContain('A recipient for error messages can be specified');
    });
});
