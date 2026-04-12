<?php

namespace Database\Seeders;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemorySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->oldest('id')->first() ?? User::factory()->create();

        // Create memories for the last 400 days.
        for ($i = 0; $i < 400; $i++) {
            $date = now()->subDays($i);
            Memory::factory()->for($user)->create([
                'memory_date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }
}
