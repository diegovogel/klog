<?php

namespace App\Logging;

use App\Mail\ErrorOccurred;
use App\Services\MaintainerResolverService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailLogHandler extends AbstractProcessingHandler
{
    /**
     * Guard against infinite recursion. Static because the recursion
     * guard must be global even if the handler is instantiated multiple times.
     */
    private static bool $sending = false;

    private const THROTTLE_SECONDS = 900; // 15 minutes

    public function __construct(
        Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (self::$sending) {
            return;
        }

        $cacheKey = 'error_email:'.md5($record->message);

        try {
            if (Cache::has($cacheKey)) {
                return;
            }
        } catch (\Throwable) {
            // Cache may not be available during bootstrapping
        }

        self::$sending = true;

        try {
            $this->sendErrorEmail($record);

            try {
                Cache::put($cacheKey, true, self::THROTTLE_SECONDS);
            } catch (\Throwable) {
                // Cache may not be available
            }
        } finally {
            self::$sending = false;
        }
    }

    private function sendErrorEmail(LogRecord $record): void
    {
        $resolver = app(MaintainerResolverService::class);

        $mailable = new ErrorOccurred(
            errorMessage: $record->message,
            errorLevel: $record->level->name,
            occurredAt: $record->datetime->format('Y-m-d H:i:s T'),
            stackTrace: $this->extractStackTrace($record),
        );

        $maintainerEmail = $resolver->resolve();
        if ($maintainerEmail !== null) {
            try {
                Mail::to($maintainerEmail)->send($mailable);

                return;
            } catch (TransportExceptionInterface) {
                // Fall through to user iteration
            }
        }

        $userEmails = $resolver->getUserEmailsInOrder();
        foreach ($userEmails as $email) {
            try {
                Mail::to($email)->send($mailable);
                $resolver->saveDiscoveredEmail($email);

                return;
            } catch (TransportExceptionInterface) {
                continue;
            }
        }
    }

    private function extractStackTrace(LogRecord $record): ?string
    {
        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof \Throwable) {
            return $exception->getTraceAsString();
        }

        return null;
    }
}
