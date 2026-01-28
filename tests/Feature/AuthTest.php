<?php

use App\Models\User;

describe('login', function () {
    it('should show the login form to guests', function () {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('Log in');
    });

    it('should redirect authenticated users away from login', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')
            ->assertRedirect('/');
    });

    it('should authenticate with valid credentials', function () {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    });

    it('should reject invalid credentials', function () {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('should require email and password', function () {
        $this->post('/login', [])
            ->assertSessionHasErrors(['email', 'password']);
    });

    it('should throttle after 5 failed attempts', function () {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        expect(session('errors')->get('email')[0])
            ->toContain('Too many login attempts');
    });
});

describe('logout', function () {
    it('should log out an authenticated user', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    });
});
