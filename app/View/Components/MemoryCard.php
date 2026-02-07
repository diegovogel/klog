<?php

namespace App\View\Components;

use App\Models\Media;
use App\Models\Memory;
use App\Models\WebClipping;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class MemoryCard extends Component
{
    /** @var Collection<int, Media>|null */
    public ?Collection $media = null;

    public ?Collection $images = null;

    public ?Collection $videos = null;

    public ?Collection $audioClips = null;

    /** @var Collection<int, WebClipping>|null */
    public ?Collection $webClippings = null;

    public function __construct(public Memory $memory)
    {
        $this->media = $memory->getMedia();
        $this->images = $this->media->where('type', 'image');
        $this->videos = $this->media->where('type', 'video');
        $this->audioClips = $this->media->where('type', 'audio');

        $this->webClippings = $memory->getWebClippings();
    }

    public function render(): View
    {
        return view('components.memory-card', [
            'memory' => $this->memory,
            'media' => $this->media,
            'images' => $this->images,
            'videos' => $this->videos,
            'audioClips' => $this->audioClips,
            'webClippings' => $this->webClippings,
        ]);
    }
}
