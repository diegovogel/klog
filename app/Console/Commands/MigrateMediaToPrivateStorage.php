<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateMediaToPrivateStorage extends Command
{
    protected $signature = 'media:migrate-to-private';

    protected $description = 'Move media files from public to private storage';

    public function handle(): int
    {
        $media = Media::withTrashed()->where('disk', 'public')->get();

        if ($media->isEmpty()) {
            $this->info('No media files on public disk. Nothing to migrate.');

            return self::SUCCESS;
        }

        $this->info("Found {$media->count()} media file(s) to migrate.");

        $publicDisk = Storage::disk('public');
        $localDisk = Storage::disk('local');
        $migrated = 0;
        $skipped = 0;

        foreach ($media as $item) {
            if (! $publicDisk->exists($item->path)) {
                $this->warn("File not found on public disk: {$item->path} (ID: {$item->id}). Updating disk column only.");
                $item->update(['disk' => 'local']);
                $skipped++;

                continue;
            }

            $localDisk->put($item->path, $publicDisk->get($item->path));
            $item->update(['disk' => 'local']);
            $publicDisk->delete($item->path);
            $migrated++;

            $this->line("  Migrated: {$item->path}");
        }

        $this->info("Migration complete. Migrated: {$migrated}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
