<div class="admin-asset-picker" data-asset-picker data-upload-url="{{ route('admin.media.upload') }}" hidden>
    <section class="admin-asset-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-asset-picker-title" tabindex="-1">
        <header>
            <div><p class="eyebrow accent">Public assets</p><h2 id="admin-asset-picker-title">Choose media</h2><p>Browse existing images and videos, or upload from your device.</p></div>
            <button class="admin-media-modal-close" type="button" aria-label="Close media library" data-asset-picker-close>×</button>
        </header>
        <div class="admin-asset-picker-toolbar">
            <label><span class="visually-hidden">Search assets</span><input type="search" placeholder="Search by name or path…" data-asset-picker-search></label>
            <label class="button button-primary admin-asset-picker-upload">
                Upload media
                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif,video/mp4,video/webm,video/quicktime" data-asset-picker-upload>
            </label>
        </div>
        <p class="admin-asset-picker-status" data-asset-picker-status aria-live="polite"></p>
        <div class="admin-asset-picker-grid" data-asset-picker-grid>
            @foreach($mediaAssets as $asset)
                <button class="admin-asset-picker-item" type="button" data-asset-select data-asset-kind="{{ $asset['kind'] }}" data-asset-name="{{ $asset['name'] }}" data-asset-path="{{ $asset['path'] }}" data-asset-url="{{ $asset['url'] }}">
                    <span class="admin-asset-picker-preview">
                        @if($asset['kind'] === 'video')
                            <video src="{{ $asset['url'] }}" muted playsinline preload="metadata"></video>
                            <span aria-hidden="true">▶</span>
                        @else
                            <img src="{{ $asset['url'] }}" alt="" loading="lazy">
                        @endif
                    </span>
                    <strong>{{ $asset['name'] }}</strong>
                    <small>{{ $asset['kind'] }} · {{ number_format($asset['size'] / 1024, 0) }} KB</small>
                </button>
            @endforeach
        </div>
        <p class="admin-asset-picker-empty" data-asset-picker-empty @if($mediaAssets->isNotEmpty()) hidden @endif>No media files yet. Upload an image or video to get started.</p>
    </section>
</div>
