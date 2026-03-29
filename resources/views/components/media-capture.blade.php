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
    <div class="media-capture__dialog-content">
        <div class="media-capture__dialog-header">
            <button type="button" data-capture-close class="btn btn--ghost btn--sm" aria-label="Close">&times;</button>
            <span data-capture-title>Record</span>
        </div>

        <div data-capture-body class="media-capture__body">
            <div class="media-capture__preview-container" hidden>
                <video data-capture-video-preview class="media-capture__video-preview" autoplay muted playsinline hidden></video>
                <canvas data-capture-canvas hidden></canvas>
                <img data-capture-photo-preview class="media-capture__photo-preview" alt="Captured photo" hidden>
                <button type="button" data-capture-switch class="media-capture__switch" aria-label="Switch camera" hidden>&#x21C5;</button>
            </div>
            <canvas data-capture-waveform class="media-capture__waveform" width="300" height="80" hidden></canvas>
            <div data-capture-timer class="media-capture__timer" hidden>0:00</div>
            <p data-capture-error class="media-capture__error" hidden></p>
        </div>

        <div class="media-capture__footer">
            <button type="button" data-capture-start class="media-capture__control btn btn--primary" hidden>Start</button>
            <button type="button" data-capture-stop class="media-capture__control media-capture__control--stop btn btn--danger" hidden>Stop</button>
            <button type="button" data-capture-snap class="media-capture__control btn btn--primary" hidden>Capture</button>
            <button type="button" data-capture-retake class="media-capture__control btn btn--secondary" hidden>Retake</button>
            <button type="button" data-capture-use class="media-capture__control btn btn--primary" hidden>Use Photo</button>
        </div>
    </div>
</dialog>
