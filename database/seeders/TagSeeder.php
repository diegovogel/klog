<?php

namespace Database\Seeders;

use App\Models\Memory;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $memories = Memory::all();

        if ($memories->count() > 0) {
            Tag::factory()->count(ceil($memories->count() / 10))->create();

            foreach ($memories as $memory) {
                $tagCount = rand(0, 5);
                $memory->attachTagNames(Tag::inRandomOrder()->take($tagCount)->pluck('name')->toArray());
            }
        } else {
            Tag::factory()->count(100)->create();
        }
    }
}
