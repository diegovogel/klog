<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Diego Vogel',
            'email' => 'diego@birdboar.co',
            'password' => Hash::make('pass'),
            'role' => UserRole::ADMIN,
        ]);

        $this->call([
            MemorySeeder::class,
            WebClippingSeeder::class,
            MediaSeeder::class,
            TagSeeder::class,
        ]);
    }
}
