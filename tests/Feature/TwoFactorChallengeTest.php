<?php

use App\Enums\TwoFactorMethod;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Mail;

describe('two-factor challenge', function () {
    describe('middleware redirect', function () {
        it('redirects users with 2fa enabled to the challenge page', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)->get('/')
                ->assertRedirect(route('two-factor.challenge'));
        });

        it('allows users without 2fa to access protected routes', function () {
            $user = User::factory()->create();

            $this->actingAs($user)->get('/')
                ->assertSuccessful();
        });

        it('allows access when session has two_factor_confirmed', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)
                ->withSession(['two_factor_confirmed' => true])
                ->get('/')
                ->assertSuccessful();
        });

        it('allows access with a valid remember cookie', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $service = app(TwoFactorService::class);
            $token = $service->generateRememberToken($user);

            $this->actingAs($user)
                ->withCookie('two_factor_remember', $token)
                ->get('/')
                ->assertSuccessful();
        });

        it('redirects with an invalid remember cookie', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)
                ->withCookie('two_factor_remember', 'invalid-token')
                ->get('/')
                ->assertRedirect(route('two-factor.challenge'));
        });

        it('keeps device A verified after device B logs in with remember', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();
            $service = app(TwoFactorService::class);

            $deviceAToken = $service->generateRememberToken($user);
            $service->generateRememberToken($user);

            $this->actingAs($user)
                ->withCookie('two_factor_remember', $deviceAToken)
                ->get('/')
                ->assertSuccessful();
        });
    });

    describe('show challenge', function () {
        it('shows the challenge page for email 2fa users', function () {
            Mail::fake();

            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)
                ->get(route('two-factor.challenge'))
                ->assertSuccessful()
                ->assertSee('A verification code has been sent to your email.');

            Mail::assertSent(TwoFactorCodeMail::class);
        });

        it('shows the challenge page for authenticator users without sending email', function () {
            Mail::fake();

            $user = User::factory()->withTwoFactor(TwoFactorMethod::AUTHENTICATOR)->create();

            $this->actingAs($user)
                ->get(route('two-factor.challenge'))
                ->assertSuccessful()
                ->assertSee('Enter the code from your authenticator app.');

            Mail::assertNothingSent();
        });

        it('redirects to home if 2fa is not enabled', function () {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->get(route('two-factor.challenge'))
                ->assertRedirect('/');
        });

        it('redirects to home if already confirmed', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)
                ->withSession(['two_factor_confirmed' => true])
                ->get(route('two-factor.challenge'))
                ->assertRedirect('/');
        });
    });

    describe('verify code', function () {
        it('verifies a valid email code and redirects to home', function () {
            Mail::fake();

            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();
            $service = app(TwoFactorService::class);
            $code = $service->issueEmailCode($user);

            $this->actingAs($user)
                ->post(route('two-factor.verify'), [
                    'code' => $code,
                    'recovery' => false,
                ])
                ->assertRedirect('/');

            $this->actingAs($user)
                ->withSession(['two_factor_confirmed' => true])
                ->get('/')
                ->assertSuccessful();
        });

        it('rejects an invalid code', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)
                ->post(route('two-factor.verify'), [
                    'code' => '000000',
                    'recovery' => false,
                ])
                ->assertSessionHasErrors('code');
        });

        it('verifies a valid recovery code', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $codes = TwoFactorService::generateRecoveryCodes();
            $user->update([
                'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes($codes),
            ]);

            $this->actingAs($user)
                ->post(route('two-factor.verify'), [
                    'code' => $codes[0],
                    'recovery' => true,
                ])
                ->assertRedirect('/');
        });

        it('sets a remember cookie when requested', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();
            $service = app(TwoFactorService::class);
            $code = $service->issueEmailCode($user);

            $response = $this->actingAs($user)
                ->post(route('two-factor.verify'), [
                    'code' => $code,
                    'recovery' => false,
                    'remember' => true,
                ]);

            $response->assertRedirect('/')
                ->assertCookie('two_factor_remember');
        });

        it('does not set a remember cookie when not requested', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();
            $service = app(TwoFactorService::class);
            $code = $service->issueEmailCode($user);

            $response = $this->actingAs($user)
                ->post(route('two-factor.verify'), [
                    'code' => $code,
                    'recovery' => false,
                ]);

            $response->assertRedirect('/')
                ->assertCookieMissing('two_factor_remember');
        });

        it('rate limits after too many failed attempts', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();
            $maxAttempts = config('klog.two_factor.max_attempts', 5);

            for ($i = 0; $i < $maxAttempts; $i++) {
                $this->actingAs($user)
                    ->post(route('two-factor.verify'), [
                        'code' => '000000',
                        'recovery' => false,
                    ]);
            }

            $this->actingAs($user)
                ->post(route('two-factor.verify'), [
                    'code' => '000000',
                    'recovery' => false,
                ])
                ->assertSessionHasErrors('code')
                ->assertSessionHas('errors', function ($errors) {
                    return str_contains($errors->first('code'), 'Too many attempts');
                });
        });
    });

    describe('resend code', function () {
        it('resends an email code', function () {
            Mail::fake();

            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)
                ->post(route('two-factor.resend'))
                ->assertRedirect()
                ->assertSessionHas('status', 'A new code has been sent to your email.');

            Mail::assertSent(TwoFactorCodeMail::class);
        });

        it('does not send for authenticator method', function () {
            Mail::fake();

            $user = User::factory()->withTwoFactor(TwoFactorMethod::AUTHENTICATOR)->create();

            $this->actingAs($user)
                ->post(route('two-factor.resend'))
                ->assertRedirect();

            Mail::assertNothingSent();
        });

        it('rate limits resend to 5 per minute', function () {
            Mail::fake();

            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            for ($i = 0; $i < 5; $i++) {
                $this->actingAs($user)
                    ->post(route('two-factor.resend'))
                    ->assertRedirect();
            }

            $this->actingAs($user)
                ->post(route('two-factor.resend'))
                ->assertStatus(429);
        });
    });

    describe('logout access', function () {
        it('allows logout without completing 2fa challenge', function () {
            $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

            $this->actingAs($user)
                ->post(route('logout'))
                ->assertRedirect('/login');

            $this->assertGuest();
        });
    });
});
