<article class="memory-card">
    <header class="memory-card__header">
        <h1 class="memory-card__title">{{$memory->title}}</h1>

        <span class="memory-card__types">
            {{implode(', ', $memory->types)}}
        </span>
    </header>

    @if($memory->content)
        <div class="memory-card__text">{!! $memory->content !!}</div>
    @endif

    @foreach($webClippings as $webClipping)
        <div class="memory-card__clipping">
            <a href="{{$webClipping->url}}">{{ $webClipping->title ?? $webClipping->url }}</a>

            @if($webClipping->screenshot)
                <button type="button" class="memory-card__clipping-screenshot-btn" onclick="const d=this.nextElementSibling;d.showModal();document.body.classList.add('scroll-disabled')">
                    <img src="{{$webClipping->screenshot->url}}"
                         alt="A screenshot of the URL"
                         class="memory-card__clipping-thumbnail">
                </button>
                <dialog class="clipping-screenshot-dialog" onclick="if(event.target===this)this.close()" onclose="document.body.classList.remove('scroll-disabled')">
                    <button type="button" class="clipping-screenshot-dialog__close" onclick="this.closest('dialog').close()">Close</button>
                    <div class="clipping-screenshot-dialog__body">
                        <img src="{{$webClipping->screenshot->url}}"
                             alt="A screenshot of the URL">
                    </div>
                </dialog>
            @endif

            @if($webClipping->content)
                <button type="button" class="memory-card__clipping-archived-btn" onclick="this.nextElementSibling.showModal()">Archived text</button>
                <dialog class="clipping-content-dialog">
                    <header class="clipping-content-dialog__header">
                        <h2>{{ $webClipping->title ?? 'Archived text' }}</h2>
                        <button type="button" onclick="this.closest('dialog').close()">Close</button>
                    </header>
                    <div class="clipping-content-dialog__body">{!! $webClipping->content !!}</div>
                </dialog>
            @endif
        </div>
    @endforeach

    @if($audioClips->count())
        <div class="memory-card__audio-clips">
            @foreach($audioClips as $clip)
                <audio controls>
                    <source src="{{$clip->url}}" type="{{$clip->mime_type}}">
                    Your browser does not support the audio element.
                </audio>
            @endforeach
        </div>
    @endif

    @if($images->count())
        <div class="memory-card__images">
            @foreach($images as $image)
                @if($image->isProcessing())
                    <div class="memory-card__media-processing">Processing image…</div>
                @elseif($image->isOptimizationFailed())
                    <div class="memory-card__media-failed">Image processing failed</div>
                @else
                    <img src="{{$image->url}}">
                @endif
            @endforeach
        </div>
    @endif

    @if($videos->count())
        <div class="memory-card__videos">
            @foreach($videos as $video)
                @if($video->isProcessing())
                    <div class="memory-card__media-processing">Processing video…</div>
                @elseif($video->isOptimizationFailed())
                    <div class="memory-card__media-failed">Video processing failed</div>
                @else
                    <video src="{{$video->url}}"
                           controls>Your browser does not support the video element.
                    </video>
                @endif
            @endforeach
        </div>
    @endif

    <footer class="memory-card__footer">
        <div class="memory-card__footer-left">
            <time datetime="{{ $memory->memory_date->toDateString() }}">{{ $memory->memory_date->format('M j, Y') }}</time>
            @if($showAuthor && $memory->user)
                <span class="memory-card__footer-pipe">|</span>
                <span class="memory-card__author">by {{ $memory->user->name }}</span>
            @endif
            @if($children->isNotEmpty())
                <span class="memory-card__footer-pipe">|</span>
                @foreach($children as $child)
                    <span class="memory-card__child-label">{{ $child->name }}</span>
                @endforeach
            @endif
        </div>
        <form method="POST" action="{{ route('memories.destroy', $memory) }}" data-confirm-delete>
            @csrf
            @method('DELETE')
            <button type="submit" class="memory-card__delete-btn">Delete</button>
        </form>
        @if($tags->isNotEmpty())
            <div class="memory-card__tags">Tags: {{ $tags->pluck('name')->implode(', ') }}</div>
        @endif
    </footer>
</article>
