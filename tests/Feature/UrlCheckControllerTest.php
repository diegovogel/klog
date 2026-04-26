<?php

use App\Models\User;
use App\Services\HostValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('redirects guests to login', function () {
    $this->get(route('url-check', ['url' => 'https://example.com']))
        ->assertRedirect(route('login'));
});

describe('url check (authenticated)', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        $this->mock(HostValidator::class)
            ->shouldReceive('resolvePublic')
            ->andReturn(['203.0.113.10']);
    });

    it('reports ok for a 2xx response', function () {
        Http::fake(['*' => Http::response('', 200)]);

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'status' => 200, 'reason' => null]);
    });

    it('follows redirects and reports the final status', function () {
        Http::fake([
            'first.example/*' => Http::response('', 301, ['Location' => 'https://second.example/final']),
            'second.example/*' => Http::response('', 200),
        ]);

        $this->getJson(route('url-check', ['url' => 'https://first.example/']))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'status' => 200, 'reason' => null]);
    });

    it('reports auth reason for 401', function () {
        Http::fake(['*' => Http::response('', 401)]);

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 401, 'reason' => 'auth']);
    });

    it('reports auth reason for 403', function () {
        Http::fake(['*' => Http::response('', 403)]);

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 403, 'reason' => 'auth']);
    });

    it('reports unreachable for 404', function () {
        Http::fake(['*' => Http::response('', 404)]);

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 404, 'reason' => 'unreachable']);
    });

    it('reports unreachable for 500', function () {
        Http::fake(['*' => Http::response('', 500)]);

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 500, 'reason' => 'unreachable']);
    });

    it('reports unreachable when the connection fails', function () {
        Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);
    });

    it('falls back to a streamed GET with a tiny Range when HEAD returns 405', function () {
        Http::fake(function ($request) {
            return $request->method() === 'HEAD'
                ? Http::response('', 405)
                : Http::response('', 200);
        });

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'status' => 200, 'reason' => null]);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->header('Range') === ['bytes=0-0']);
    });

    it('rejects missing url', function () {
        $this->getJson(route('url-check'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    });

    it('rejects non-http schemes', function () {
        $this->getJson(route('url-check', ['url' => 'ftp://example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    });

    it('rejects malformed urls', function () {
        $this->getJson(route('url-check', ['url' => 'not-a-url']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    });
});

describe('url check (SSRF protection)', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->create()));

    it('rejects loopback IPs without making any request', function () {
        Http::fake();

        $this->getJson(route('url-check', ['url' => 'http://127.0.0.1/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);

        Http::assertNothingSent();
    });

    it('rejects link-local IPs (cloud metadata service)', function () {
        Http::fake();

        $this->getJson(route('url-check', ['url' => 'http://169.254.169.254/latest/meta-data/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);

        Http::assertNothingSent();
    });

    it('rejects RFC1918 IPs (10.0.0.0/8)', function () {
        Http::fake();

        $this->getJson(route('url-check', ['url' => 'http://10.0.0.1/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);

        Http::assertNothingSent();
    });

    it('rejects RFC1918 IPs (192.168.0.0/16)', function () {
        Http::fake();

        $this->getJson(route('url-check', ['url' => 'http://192.168.1.1/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);

        Http::assertNothingSent();
    });

    it('rejects RFC1918 IPs (172.16.0.0/12)', function () {
        Http::fake();

        $this->getJson(route('url-check', ['url' => 'http://172.16.0.1/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);

        Http::assertNothingSent();
    });

    it('rejects IPv6 loopback', function () {
        Http::fake();

        $this->getJson(route('url-check', ['url' => 'http://[::1]/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);

        Http::assertNothingSent();
    });

    it('rejects when any resolved IP is private (mixed-result hostname)', function () {
        $this->mock(HostValidator::class)
            ->shouldReceive('resolvePublic')
            ->andReturn(null);

        Http::fake();

        $this->getJson(route('url-check', ['url' => 'http://rebind.attacker.example/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);

        Http::assertNothingSent();
    });

    it('allows public IP literals', function () {
        Http::fake(['*' => Http::response('', 200)]);

        $this->getJson(route('url-check', ['url' => 'http://1.1.1.1/']))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'status' => 200, 'reason' => null]);
    });

    it('accepts public IPv6 literal URLs', function () {
        Http::fake(['*' => Http::response('', 200)]);

        $this->getJson(route('url-check', ['url' => 'http://[2606:4700:4700::1111]/']))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'status' => 200, 'reason' => null]);
    });

    it('blocks redirects to non-public hosts', function () {
        $this->mock(HostValidator::class, function ($mock) {
            $mock->shouldReceive('resolvePublic')
                ->with('first.example')
                ->andReturn(['203.0.113.10']);
            $mock->shouldReceive('resolvePublic')
                ->with('internal.local')
                ->andReturn(null);
        });

        Http::fake([
            'first.example/*' => Http::response('', 302, ['Location' => 'http://internal.local/']),
        ]);

        $this->getJson(route('url-check', ['url' => 'http://first.example/']))
            ->assertOk()
            ->assertExactJson(['ok' => false, 'status' => 0, 'reason' => 'unreachable']);
    });
});

describe('HostValidator::shouldPinDns', function () {
    it('returns true for hostnames that need DNS pinning', function () {
        expect(\App\Services\HostValidator::shouldPinDns('example.com'))->toBeTrue();
    });

    it('returns false for IPv4 literal hosts', function () {
        expect(\App\Services\HostValidator::shouldPinDns('1.1.1.1'))->toBeFalse();
    });

    it('returns false for bracketed IPv6 literal hosts (no DNS lookup happens; cURL rejects bracketed --resolve entries)', function () {
        expect(\App\Services\HostValidator::shouldPinDns('[2606:4700:4700::1111]'))->toBeFalse();
    });

    it('returns false for bare IPv6 literal hosts', function () {
        expect(\App\Services\HostValidator::shouldPinDns('::1'))->toBeFalse();
    });
});

describe('HostValidator::curlResolveEntries', function () {
    it('formats host:port:ip entries for hostnames', function () {
        expect(\App\Services\HostValidator::curlResolveEntries('example.com', 443, ['1.2.3.4', '1.2.3.5']))
            ->toBe(['example.com:443:1.2.3.4', 'example.com:443:1.2.3.5']);
    });

    it('formats hostname → IPv6 IP entries without bracketing the IP', function () {
        expect(\App\Services\HostValidator::curlResolveEntries('example.com', 443, ['2606:4700::1']))
            ->toBe(['example.com:443:2606:4700::1']);
    });
});
