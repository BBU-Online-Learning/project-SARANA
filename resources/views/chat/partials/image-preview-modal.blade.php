<div id="image-preview-modal" class="image-preview-modal" role="dialog" aria-modal="true" aria-hidden="true"
    aria-labelledby="image-preview-caption" tabindex="-1">

    <div id="image-preview-status" class="image-preview-status" role="status" aria-live="polite">Loading image…</div>

    <button type="button" id="image-preview-close" class="image-preview-close" aria-label="Close preview">
        <i class="ti ti-x" aria-hidden="true"></i>
    </button>

    <button type="button" id="image-preview-previous" class="image-preview-navigation image-preview-previous"
        aria-label="Previous photo" title="Previous photo" hidden>
        <i class="ti ti-chevron-left" aria-hidden="true"></i>
    </button>
    <img id="image-preview-modal-img" src="" alt="">
    <button type="button" id="image-preview-next" class="image-preview-navigation image-preview-next"
        aria-label="Next photo" title="Next photo" hidden>
        <i class="ti ti-chevron-right" aria-hidden="true"></i>
    </button>

    <div class="image-preview-toolbar">
        <span id="image-preview-counter" class="image-preview-counter" aria-live="polite"></span>
        <div id="image-preview-caption" class="image-preview-caption"></div>
        <a id="image-preview-download" class="image-preview-download">
            <i class="ti ti-download" aria-hidden="true"></i> Download
        </a>
    </div>

</div>
