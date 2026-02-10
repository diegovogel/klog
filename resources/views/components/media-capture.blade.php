<div class="media-capture__actions" data-media-capture>
    <button type="button" data-capture-mode="audio" class="media-capture__btn">
        <span class="media-capture__btn-icon">&#9834;</span>
        Record Audio
    </button>
    <button type="button" data-capture-mode="video" class="media-capture__btn">
        <span class="media-capture__btn-icon">&#9654;</span>
        Record Video
    </button>
    <button type="button" data-capture-mode="photo" class="media-capture__btn">
        <span class="media-capture__btn-icon">&#128247;</span>
        Take Photo
    </button>
</div>

<dialog data-media-capture-dialog class="media-capture__dialog">
    <article>
        <header>
            <button type="button" data-capture-close aria-label="Close" rel="prev"></button>
            <span data-capture-title>Record</span>
        </header>

        <div data-capture-body class="media-capture__body">
            <video data-capture-video-preview class="media-capture__video-preview" autoplay muted playsinline hidden></video>
            <canvas data-capture-canvas hidden></canvas>
            <img data-capture-photo-preview class="media-capture__photo-preview" alt="Captured photo" hidden>
            <canvas data-capture-waveform class="media-capture__waveform" width="300" height="80" hidden></canvas>
            <div data-capture-timer class="media-capture__timer" hidden>0:00</div>
            <p data-capture-error class="media-capture__error" hidden></p>
        </div>

        <footer class="media-capture__footer">
            <button type="button" data-capture-start class="media-capture__control" hidden>Start</button>
            <button type="button" data-capture-stop class="media-capture__control media-capture__control--stop" hidden>Stop</button>
            <button type="button" data-capture-snap class="media-capture__control" hidden>Capture</button>
            <button type="button" data-capture-retake class="media-capture__control secondary" hidden>Retake</button>
            <button type="button" data-capture-use class="media-capture__control" hidden>Use Photo</button>
        </footer>
    </article>
</dialog>
