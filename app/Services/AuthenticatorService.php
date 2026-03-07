<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class AuthenticatorService
{
    public function isAvailable(): bool
    {
        return class_exists(\PragmaRX\Google2FA\Google2FA::class);
    }

    public function generateSecret(): string
    {
        $this->ensureAvailable();

        return (new \PragmaRX\Google2FA\Google2FA)->generateSecretKey();
    }

    public function qrCodeUri(User $user, string $secret): string
    {
        $this->ensureAvailable();

        return (new \PragmaRX\Google2FA\Google2FA)->getQRCodeUrl(
            config('app.name', 'Klog'),
            $user->email,
            $secret,
        );
    }

    public function generateQrCodeSvg(string $uri): string
    {
        $this->ensureAvailable();

        if (! class_exists(\chillerlan\QRCode\QRCode::class)) {
            throw new RuntimeException('QR code package is not installed.');
        }

        $options = new \chillerlan\QRCode\QROptions;
        $options->outputType = \chillerlan\QRCode\Common\EccLevel::L;
        $options->outputInterface = \chillerlan\QRCode\Output\QRMarkupSVG::class;
        $options->svgUseCssProperties = false;
        $options->drawLightModules = false;
        $options->addQuietzone = true;

        return (new \chillerlan\QRCode\QRCode($options))->render($uri);
    }

    public function verify(string $secret, string $code): bool
    {
        $this->ensureAvailable();

        return (new \PragmaRX\Google2FA\Google2FA)->verifyKey($secret, $code, 1);
    }

    private function ensureAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException(
                'Authenticator packages are not installed. Run: php artisan 2fa:install-authenticator'
            );
        }
    }
}
