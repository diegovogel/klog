<?php

use App\Enums\MediaType;
use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Child;
use App\Models\Media;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\User;
use App\Models\WebClipping;
use App\Services\ScreenshotFeatureService;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
});

it('creates the demo family', function () {
    $demo = User::where('email', config('klog.demo_email'))->first();

    expect($demo)->not->toBeNull()
        ->and($demo->role)->toBe(UserRole::ADMIN)
        ->and(User::count())->toBe(2)
        ->and(Child::pluck('name')->all())->toEqualCanonicalizing(['Mia', 'Theo']);
});

it('creates a believable, varied feed', function () {
    expect(Memory::count())->toBeGreaterThan(20)
        ->and(Memory::whereNotNull('content')->count())->toBeGreaterThan(20)
        ->and(Tag::where('name', 'milestones')->exists())->toBeTrue()
        ->and(Memory::has('webClippings')->count())->toBeGreaterThanOrEqual(2)
        ->and(Memory::has('children')->count())->toBeGreaterThan(20);
});

it('attaches real image files that resolve on disk', function () {
    $images = Media::where('type', MediaType::IMAGE->value)->get();

    expect($images)->not->toBeEmpty();

    foreach ($images as $image) {
        expect(Storage::disk('local')->exists($image->path))->toBeTrue();
    }
});

it('pins screenshots off so a wiped flag does not default them back on', function () {
    expect(AppSetting::getValue(ScreenshotFeatureService::ENABLED_KEY))->toBe('false')
        ->and(app(ScreenshotFeatureService::class)->isEnabled())->toBeFalse();
});

it('only seeds clipping URLs on real, resolvable domains', function () {
    expect(WebClipping::where('url', 'like', '%example.com%')->exists())->toBeFalse()
        ->and(WebClipping::where('url', 'like', '%example.org%')->exists())->toBeFalse()
        ->and(WebClipping::count())->toBeGreaterThanOrEqual(2);
});
