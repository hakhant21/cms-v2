{{--
    The shared image dialog.

    One element for the whole page: every image marked as a lightbox trigger
    opens in it, and the arrows walk through the triggers that belong to the
    same group. The script fills the image, the caption and the counter; the
    markup itself never changes.
--}}
<div
    id="lightbox"
    class="lightbox"
    role="dialog"
    aria-modal="true"
    aria-labelledby="lightbox-caption"
    hidden
    data-lightbox-dialog
>
    <button type="button" class="lightbox__backdrop" data-lightbox-close aria-label="{{ __('ui.lightbox.close') }}"></button>

    <figure class="lightbox__panel">
        <img class="lightbox__image" data-lightbox-image src="" alt="" decoding="async">

        <figcaption class="lightbox__bar">
            <span class="lightbox__counter" data-lightbox-counter></span>
            <p class="lightbox__caption" id="lightbox-caption" data-lightbox-caption></p>
        </figcaption>

        <div class="lightbox__controls">
            <button type="button" class="lightbox__nav" data-lightbox-prev aria-label="{{ __('ui.common.previous') }}">
                <x-site.icon name="arrow-left" class="h-5 w-5" />
            </button>

            <button type="button" class="lightbox__nav" data-lightbox-next aria-label="{{ __('ui.common.next') }}">
                <x-site.icon name="arrow-right" class="h-5 w-5" />
            </button>

            <a class="lightbox__nav lightbox__original" data-lightbox-original href="#" target="_blank" rel="noopener">
                {{ __('ui.lightbox.open_original') }}
            </a>

            <button type="button" class="lightbox__nav lightbox__close" data-lightbox-close aria-label="{{ __('ui.lightbox.close') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12" />
                </svg>
            </button>
        </div>
    </figure>
</div>
