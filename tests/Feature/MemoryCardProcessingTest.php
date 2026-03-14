<?php

use App\Enums\ProcessingStatus;
use App\Models\Media;
use App\Models\Memory;
use App\Models\User;

describe('memory card with processing media', function () {
    it('shows processing indicator for pending images', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        Media::factory()->heic()->create([
            'mediable_id' => $memory->id,
            'mediable_type' => $memory->getMorphClass(),
            'processing_status' => ProcessingStatus::Pending,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Processing image');
    });

    it('shows processing indicator for processing videos', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        Media::factory()->mov()->processing()->create([
            'mediable_id' => $memory->id,
            'mediable_type' => $memory->getMorphClass(),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Processing video');
    });

    it('shows failure indicator for failed images', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        Media::factory()->heic()->failed()->create([
            'mediable_id' => $memory->id,
            'mediable_type' => $memory->getMorphClass(),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Image processing failed');
    });

    it('shows failure indicator for failed videos', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        Media::factory()->mov()->failed()->create([
            'mediable_id' => $memory->id,
            'mediable_type' => $memory->getMorphClass(),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Video processing failed');
    });

    it('renders normal img tag for complete images', function () {
        $user = User::factory()->create();
        $memory = Memory::factory()->create();
        $media = Media::factory()->image()->create([
            'mediable_id' => $memory->id,
            'mediable_type' => $memory->getMorphClass(),
            'processing_status' => ProcessingStatus::Complete,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertDontSee('Processing image')
            ->assertSee($media->url);
    });
});
