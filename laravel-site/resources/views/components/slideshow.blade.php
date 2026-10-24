@props(['slides', 'alt'])

<div class="package-slideshow" role="group" aria-roledescription="carousel" aria-label="{{ $alt }} photos" data-slideshow>
    <div class="slideshow-track">
        @foreach($slides as $index => $slide)
            <figure class="slideshow-slide {{ $index === 0 ? 'is-active' : '' }}" data-slideshow-slide aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                <img src="{{ asset_img($slide['image']) }}" alt="{{ $index === 0 ? $slide['caption'] : '' }}" data-slideshow-image>
                <figcaption>{{ $slide['caption'] }}</figcaption>
            </figure>
        @endforeach
    </div>
    <div class="slideshow-shade" aria-hidden="true"></div>
    @if($slides->count() > 1)
        <button class="slideshow-arrow slideshow-prev" type="button" aria-label="Previous photo" data-slideshow-prev>←</button>
        <button class="slideshow-arrow slideshow-next" type="button" aria-label="Next photo" data-slideshow-next>→</button>
        <div class="slideshow-controls" role="group" aria-label="Choose photo">
            <div class="slideshow-dots">
                @foreach($slides as $index => $slide)
                    <button type="button" aria-label="Show photo {{ $index + 1 }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}" data-slide-index="{{ $index }}" data-slideshow-dot></button>
                @endforeach
            </div>
            <button class="slideshow-pause" type="button" aria-label="Pause slideshow" aria-pressed="false" data-slideshow-pause>Pause</button>
        </div>
    @endif
</div>
