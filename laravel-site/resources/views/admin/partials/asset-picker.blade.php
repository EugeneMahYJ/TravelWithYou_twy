<div class="admin-asset-picker" data-asset-picker hidden>
    <section class="admin-asset-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-asset-picker-title" tabindex="-1">
        <header>
            <div><p class="eyebrow accent">Public assets</p><h2 id="admin-asset-picker-title">Choose media</h2><p data-asset-picker-description>Browse folders and select media for this item.</p></div>
            <button class="admin-media-modal-close" type="button" aria-label="Close media library" data-asset-picker-close>×</button>
        </header>
        <div class="admin-asset-picker-toolbar">
            <label><span class="visually-hidden">Search assets</span><input type="search" placeholder="Search by name or path…" data-asset-picker-search></label>
        </div>
        <p class="admin-asset-picker-status" data-asset-picker-status aria-live="polite"></p>
        <div class="admin-asset-picker-body">
            <aside class="admin-asset-picker-folders" aria-label="Media folders">
                <h3>Folders</h3>
                <nav data-asset-picker-folders aria-label="Folder navigation">
                    <a href="#" class="is-active" data-asset-folder="">All media <span>{{ $mediaAssets->count() }}</span></a>
                    @foreach($mediaFolders as $folder)
                        <a href="#" style="--folder-depth: {{ substr_count($folder, '/') }}" data-asset-folder="{{ $folder }}">
                            {{ basename($folder) }}
                            <span>{{ $mediaAssets->filter(fn (array $asset): bool => dirname($asset['path']) === $folder)->count() }}</span>
                        </a>
                    @endforeach
                </nav>
                <p>Choose a folder, then select an image.</p>
            </aside>
            <section class="admin-asset-picker-browser" aria-label="Available media">
                <div class="admin-asset-picker-browser-toolbar">
                    <p data-asset-picker-breadcrumb>All media</p>
                    <div class="admin-asset-picker-filters" role="group" aria-label="Filter media type">
                        <button type="button" class="is-active" data-asset-kind-filter="all">All</button>
                        <button type="button" data-asset-kind-filter="image">Images</button>
                        <button type="button" data-asset-kind-filter="video">Videos</button>
                    </div>
                </div>
                <p class="admin-asset-picker-count" data-asset-picker-count></p>
                <div class="admin-asset-picker-grid" data-asset-picker-grid>
                    @foreach($mediaAssets as $asset)
                        <button class="admin-asset-picker-item" type="button" data-asset-select data-asset-kind="{{ $asset['kind'] }}" data-asset-name="{{ $asset['name'] }}" data-asset-path="{{ $asset['path'] }}" data-asset-url="{{ $asset['url'] }}" data-asset-folder="{{ dirname($asset['path']) === '.' ? '' : dirname($asset['path']) }}">
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
                <p class="admin-asset-picker-empty" data-asset-picker-empty @if($mediaAssets->isNotEmpty()) hidden @endif>No media files yet.</p>
            </section>
        </div>
        <footer class="admin-asset-picker-footer">
            <p data-asset-picker-selection>No media selected</p>
            <div>
                <button class="button button-ghost" type="button" data-asset-picker-close>Cancel</button>
                <button class="button button-primary" type="button" data-asset-picker-confirm disabled>Use selected media</button>
            </div>
        </footer>
    </section>
</div>
