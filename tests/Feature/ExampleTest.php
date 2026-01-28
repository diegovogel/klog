<?php

use App\Models\User;

test('the application redirects guests to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('the application returns a successful response for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
});
