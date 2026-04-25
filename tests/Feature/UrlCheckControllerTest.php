<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('redirects guests to login', function () {
    $this->get(route('url-check', ['url' => 'https://example.com']))
        ->assertRedirect(route('login'));
});

describe('url check (authenticated)', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->create()));

    it('reports ok for a 2xx response', function () {
        Http::fake(['*' => Http::response('', 200)]);

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'status' => 200, 'reason' => null]);
    });

    it('reports ok for a 3xx response (redirects already followed by client)', function () {
        Http::fake(['*' => Http::response('', 301)]);

        $this->getJson(route('url-check', ['url' => 'https://example.com']))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'status' => 301, 'reason' => null]);
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

    it('falls back to GET with a tiny Range when HEAD returns 405', function () {
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
