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
                <img src="{{$image->url}}">
            @endforeach
        </div>
    @endif

    @if($videos->count())
        <div class="memory-card__videos">
            @foreach($videos as $video)
                <video src="{{$video->url}}"
                       controls>Your browser does not support the audio element.
                </video>
            @endforeach
        </div>
    @endif
</article>
