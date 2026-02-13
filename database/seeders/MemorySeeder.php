<?php

namespace Database\Seeders;

use App\Models\Memory;
use Illuminate\Database\Seeder;

class MemorySeeder extends Seeder
{
    public function run(): void
    {
        // Create memories for the last 400 days.
        for ($i = 0; $i < 400; $i++) {
            $date = now()->subDays($i);
            Memory::factory()->create([
                'memory_date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }
}
