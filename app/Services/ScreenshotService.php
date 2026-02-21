<?php

namespace App\Services;

class ScreenshotService
{
    /**
     * Check if the Browsershot package is installed and available.
     */
    public function isAvailable(): bool
    {
        return class_exists(\Spatie\Browsershot\Browsershot::class);
    }

    /**
     * Capture a screenshot of a URL and save it to a temporary file.
     *
     * @return string Path to the temporary PNG file.
     *
     * @throws \Throwable If screenshot capture fails.
     */
    public function capture(string $url): string
    {
        $tempPath = sys_get_temp_dir().'/'.uniqid('klog_screenshot_').'.png';

        \Spatie\Browsershot\Browsershot::url($url)
            ->windowSize(1280, 960)
            ->fullPage()
            ->waitUntilNetworkIdle(false)
            ->setOption('args', ['--no-sandbox'])
            ->userAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36')
            ->setOption('addStyleTag', json_encode(['content' => self::overlayHidingCss()]))
            ->setOption('addScriptTag', json_encode(['content' => self::overlayDismissalScript()]))
            ->delay(1000)
            ->save($tempPath);

        return $tempPath;
    }

    /**
     * CSS that hides common cookie banners, consent overlays, and sticky bars.
     *
     * Applied as a blanket safety net — even if the JS dismissal script
     * successfully clicks a dismiss button, some overlays animate out slowly.
     */
    private static function overlayHidingCss(): string
    {
        return <<<'CSS'
        [class*="cookie" i],
        [id*="cookie" i],
        [class*="consent" i],
        [id*="consent" i],
        [class*="gdpr" i],
        [id*="gdpr" i],
        [class*="notice-bar" i],
        [class*="privacy-banner" i],
        [id*="privacy-banner" i],
        [class*="privacy-modal" i],
        [id*="privacy-modal" i],
        [class*="onetrust" i],
        [id*="onetrust" i],
        [id*="CybotCookiebot" i],
        [class*="CybotCookiebot" i],
        [id*="sp_message_container" i],
        [class*="evidon" i],
        [id*="evidon" i],
        [class*="truste" i],
        [id*="truste" i],
        [aria-label*="cookie" i],
        [aria-label*="consent" i],
        [aria-label*="privacy" i],
        [class*="cc-banner" i],
        [id*="cc-banner" i] {
            display: none !important;
            visibility: hidden !important;
        }
        CSS;
    }

    /**
     * JavaScript that attempts to dismiss cookie/consent overlays by clicking
     * common accept/dismiss buttons. Wrapped in try/catch so failures are silent.
     */
    private static function overlayDismissalScript(): string
    {
        return <<<'JS'
        (function() {
            try {
                // Phase 1: Try clicking common dismiss buttons by selector.
                const selectors = [
                    '[class*="cookie" i] button',
                    '[id*="cookie" i] button',
                    '[class*="consent" i] button',
                    '[id*="consent" i] button',
                    'button[class*="accept" i]',
                    'button[id*="accept" i]',
                    'a[class*="accept" i]',
                    '[class*="cookie" i] a',
                    'button[class*="agree" i]',
                    'button[class*="dismiss" i]',
                    'button[class*="close-banner" i]',
                    'button[aria-label*="accept" i]',
                    'button[aria-label*="dismiss" i]',
                    'button[aria-label*="close" i][aria-label*="cookie" i]',
                    '.onetrust-accept-btn-handler',
                    '#onetrust-accept-btn-handler',
                    '#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll',
                    '[data-testid="cookie-policy-manage-dialog-btn-accept-all"]',
                ];

                let clicked = false;
                for (const selector of selectors) {
                    const el = document.querySelector(selector);
                    if (el && el.offsetParent !== null) {
                        el.click();
                        clicked = true;
                        break;
                    }
                }

                // Phase 2: If no selector matched, search by button text content.
                if (!clicked) {
                    const dismissTexts = [
                        'accept all', 'accept cookies', 'allow all', 'allow cookies',
                        'i agree', 'agree', 'got it', 'ok', 'okay',
                        'continue shopping', 'continue browsing', 'dismiss',
                        'deny non-essential', 'reject all', 'deny all',
                        'close', 'no thanks',
                    ];
                    const buttons = document.querySelectorAll('button, a[role="button"], [role="button"]');
                    for (const btn of buttons) {
                        const text = (btn.textContent || '').trim().toLowerCase();
                        if (text && dismissTexts.some(d => text === d || text.startsWith(d))) {
                            if (btn.offsetParent !== null) {
                                btn.click();
                                break;
                            }
                        }
                    }
                }

                // Phase 3: Remove any remaining fixed/sticky overlays that cover the viewport.
                document.querySelectorAll('div, section, aside').forEach(el => {
                    const style = window.getComputedStyle(el);
                    if ((style.position === 'fixed' || style.position === 'sticky') && parseFloat(style.zIndex) > 999) {
                        const rect = el.getBoundingClientRect();
                        if (rect.width > window.innerWidth * 0.5 || rect.height > window.innerHeight * 0.3) {
                            el.remove();
                        }
                    }
                });
            } catch (e) {
                // Silent — overlay dismissal is best-effort.
            }
        })();
        JS;
    }

    /**
     * Test the screenshot pipeline by rendering a simple HTML page.
     *
     * @throws \Throwable If the pipeline is broken (Chromium missing, etc.).
     */
    public function testPipeline(): bool
    {
        $tempPath = sys_get_temp_dir().'/'.uniqid('klog_test_').'.png';

        try {
            \Spatie\Browsershot\Browsershot::html('<html><body><h1>Klog Screenshot Test</h1></body></html>')
                ->save($tempPath);

            $result = file_exists($tempPath) && filesize($tempPath) > 0;

            @unlink($tempPath);

            return $result;
        } catch (\Throwable $e) {
            @unlink($tempPath);

            throw $e;
        }
    }
}
