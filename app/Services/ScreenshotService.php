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
            ->waitUntilNetworkIdle()
            ->save($tempPath);

        return $tempPath;
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
