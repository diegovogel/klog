<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckUrlRequest;
use App\Services\HostValidator;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class UrlCheckController extends Controller
{
    private const TIMEOUT = 5;

    private const CONNECT_TIMEOUT = 3;

    /** @var array<int, int> Status codes that indicate authentication is required. */
    private const AUTH_STATUSES = [401, 403, 407];

    /** @var array<int, int> Status codes worth retrying with GET when HEAD is unsupported. */
    private const RETRY_WITH_GET = [405, 501];

    private const MAX_REDIRECTS = 5;

    public function __construct(private HostValidator $hostValidator) {}

    public function check(CheckUrlRequest $request): JsonResponse
    {
        try {
            $status = $this->probe($request->validated('url'));
        } catch (\Throwable) {
            return $this->result(0, 'unreachable');
        }

        if ($status >= 200 && $status < 300) {
            return $this->result($status, null);
        }

        return $this->result(
            $status,
            in_array($status, self::AUTH_STATUSES, true) ? 'auth' : 'unreachable',
        );
    }

    private function result(int $status, ?string $reason): JsonResponse
    {
        return response()->json([
            'ok' => $reason === null,
            'status' => $status,
            'reason' => $reason,
        ]);
    }

    /**
     * Follow redirects manually so each hop validates its own host and pins
     * its own DNS via CURLOPT_RESOLVE. Guzzle's built-in allow_redirects only
     * lets us validate hosts in the on_redirect callback, which leaves a DNS
     * rebinding window between callback time and cURL's connection time.
     */
    private function probe(string $url): int
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = $this->probeOnce($current);
            $status = $response->status();

            if ($status < 300 || $status >= 400) {
                return $status;
            }

            $location = $response->header('Location');
            if ($location === '') {
                return $status;
            }

            if ($hop === self::MAX_REDIRECTS) {
                throw new \RuntimeException('Too many redirects');
            }

            $current = (string) UriResolver::resolve(new Uri($current), new Uri($location));
        }

        throw new \RuntimeException('Redirect loop guard exceeded');
    }

    private function probeOnce(string $url): Response
    {
        $client = $this->buildClient($url);
        $head = $client->head($url);

        if (in_array($head->status(), self::RETRY_WITH_GET, true)) {
            return $client
                ->withHeaders(['Range' => 'bytes=0-0'])
                ->withOptions(['stream' => true])
                ->get($url);
        }

        return $head;
    }

    private function buildClient(string $url): PendingRequest
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! is_string($host) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Invalid URL');
        }

        $ips = $this->hostValidator->resolvePublic($host);
        if ($ips === null) {
            throw new \RuntimeException('Non-public host rejected');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $options = ['allow_redirects' => false];

        if (HostValidator::shouldPinDns($host)) {
            $options['curl'] = [
                CURLOPT_RESOLVE => HostValidator::curlResolveEntries($host, $port, $ips),
            ];
        }

        return Http::timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withUserAgent('Klog/1.0 (URL check)')
            ->withOptions($options);
    }
}
