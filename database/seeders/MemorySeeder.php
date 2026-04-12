<?php

namespace Database\Seeders;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemorySeeder extends Seeder
{
    public function run(): void
    {
        // Ensure at least two users exist so seeded memories span multiple
        // authors (useful for exercising the search page's author filter).
        $missing = max(0, 2 - User::query()->count());
        if ($missing > 0) {
            User::factory()->count($missing)->create();
        }

        $userIds = User::query()->pluck('id')->all();

        // Create memories for the last 400 days.
        for ($i = 0; $i < 400; $i++) {
            $date = now()->subDays($i);
            Memory::factory()->create([
                'user_id' => $userIds[array_rand($userIds)],
                'memory_date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }
}
