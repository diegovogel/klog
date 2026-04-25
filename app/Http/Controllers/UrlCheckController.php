<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckUrlRequest;
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

    public function check(CheckUrlRequest $request): JsonResponse
    {
        try {
            $status = $this->probe($request->validated('url'));
        } catch (\Throwable) {
            return $this->result(0, 'unreachable');
        }

        if ($status >= 200 && $status < 400) {
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

    private function probe(string $url): int
    {
        $client = Http::timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withUserAgent('Klog/1.0 (URL check)');

        $head = $client->head($url);

        if (in_array($head->status(), self::RETRY_WITH_GET, true)) {
            return $client->withHeaders(['Range' => 'bytes=0-0'])->get($url)->status();
        }

        return $head->status();
    }
}
