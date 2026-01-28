<?php

namespace Database\Seeders;

use App\Models\Memory;
use App\Models\WebClipping;
use Database\Factories\WebClippingFactory;
use Illuminate\Database\Seeder;

class WebClippingSeeder extends Seeder
{
    public function run(): void
    {
        $memories = Memory::all();

        if ($memories->count() > 0) {
            foreach ($memories as $memory) {
                $hasClipping = rand(1, 10) <= 2; // 20% of memories have clippings.

                if ($hasClipping) {
                    $memory->webClippings()->createMany(WebClippingFactory::new()->count(rand(1, 3))->make()->toArray());
                }
            }
        } else {
            WebClipping::factory()->count(100)->create();
        }
    }
}
