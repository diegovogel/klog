<?php

namespace Database\Seeders;

use App\Models\Memory;
use Illuminate\Database\Seeder;

class MemorySeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 0; $i < 400; $i++) {
            $date = now()->subDays($i);
            Memory::factory()->create([
                'captured_at' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }
}
