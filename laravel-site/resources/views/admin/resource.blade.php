@extends('admin.layouts.admin')
@section('title', $title)
@section('content')
<div class="admin-heading {{ $resource === 'deals' ? 'admin-deals-heading' : '' }}"><div><p class="eyebrow accent">Workspace</p><h1>{{ $title }}</h1><p>@if($resource === 'deals')Manage offer terms and availability.@elseif($resource === 'bookings')Review traveller bookings.@elseif($resource === 'enquiries')Reply to traveller enquiries.@elseif($resource === 'guides')Manage published travel guides.@elseif($resource === 'media')Images and videos stored in public/assets.@elseif($resource === 'users')Traveller accounts and booking history.@elseManage company details and contact information.@endif</p></div>
    @if($resource === 'deals')<div class="admin-deal-create-actions"><a class="button button-ghost admin-new-deal-button" href="{{ route('admin.deals.create') }}">New deal</a><a class="button button-primary admin-new-promo-button" href="{{ route('admin.promos.create') }}">New Promo</a></div>@endif
    @if($resource === 'guides')<a class="button button-primary" href="{{ route('admin.guides.create') }}">New guide</a>@endif
</div>
<section class="admin-panel">
    @if($resource === 'company')
        <div class="company-settings"><h2>Travel With You</h2><p><strong>Phone:</strong> {{ config('company.phone') }}</p><p><strong>Email:</strong> {{ config('company.email') }}</p><p><strong>Address:</strong> {!! nl2br(e(config('company.address'))) !!}</p><p><strong>Hours:</strong> {!! nl2br(e(config('company.hours'))) !!}</p><a class="button button-ghost" href="{{ route('contact') }}">View contact page</a></div>

    @elseif($resource === 'deals')
        <form class="admin-filter-form" method="get" action="{{ route('admin.deals') }}"><label><span class="visually-hidden">Search deals</span><input name="q" type="search" value="{{ request('q') }}" placeholder="Search deals…"></label><button class="button button-ghost" type="submit">Search</button></form>
        @if($promoCodes->isNotEmpty())
            <section class="admin-promo-section">
                <h2>Promo codes</h2>
                <div class="admin-deal-grid">@foreach($promoCodes as $promoCode)
                    @php
                        $promoValue = $promoCode->discount_type === 'fixed'
                            ? fmt_rm($promoCode->discount_amount)
                            : rtrim(rtrim(number_format((float) $promoCode->discount_percent, 2, '.', ''), '0'), '.').'%';
                        $eligiblePackages = $promoCode->all_packages ? 'All packages' : $promoCode->packages->pluck('name')->join(', ');
                    @endphp
                    <article class="admin-deal-card">
                        <div class="admin-deal-card-top"><span class="eyebrow accent">Promo code</span><span class="status-pill">{{ $promoCode->active ? 'Active' : 'Paused' }}</span></div>
                        <strong class="admin-deal-percent">{{ $promoValue }}</strong>
                        <h2>{{ $promoCode->code }}</h2>
                        <p>{{ $eligiblePackages ?: 'No eligible packages selected' }}</p>
                        @if($promoCode->terms)<p class="muted">{{ $promoCode->terms }}</p>@endif
                        <p class="muted">@if($promoCode->starts_at)Starts {{ $promoCode->starts_at->format('j M Y, H:i') }} · @endif{{ $promoCode->expires_at ? 'Expires '.$promoCode->expires_at->format('j M Y, H:i') : 'No expiry date' }}</p>
                        <div class="admin-deal-actions">
                            <a class="button button-ghost" href="{{ route('admin.promos.edit', $promoCode) }}">Edit</a>
                            <form method="post" action="{{ route('admin.promos.toggle', $promoCode) }}">@csrf @method('patch')<button class="button button-ghost" type="submit">{{ $promoCode->active ? 'Pause' : 'Resume' }}</button></form>
                            <form method="post" action="{{ route('admin.promos.delete', $promoCode) }}" onsubmit="return confirm('Delete this promo code?')">@csrf @method('delete')<button class="button button-ghost danger" type="submit">Delete</button></form>
                        </div>
                    </article>
                @endforeach</div>
            </section>
        @endif
        <div class="admin-deal-grid">@forelse($rows as $deal)
            <article class="admin-deal-card"><div class="admin-deal-card-top"><span class="eyebrow accent">{{ $deal->type ?: 'Promo code' }}</span><span class="status-pill">{{ $deal->active ? 'Active' : 'Paused' }}</span></div><strong class="admin-deal-percent">{{ $deal->discount_percent }}%</strong><h2>{{ $deal->name }}</h2><p>{{ $deal->package?->name ?? 'All packages' }}</p><p class="muted">Ends {{ $deal->expires_at?->format('j M Y, H:i') ?? $deal->ends_at?->format('j M Y') ?? 'without end date' }}</p><div class="admin-deal-actions"><a class="button button-ghost" href="{{ route('admin.deals.edit', $deal) }}">Edit</a><form method="post" action="{{ route('admin.deals.toggle', $deal) }}">@csrf @method('patch')<button class="button button-ghost" type="submit">{{ $deal->active ? 'Pause' : 'Resume' }}</button></form><form method="post" action="{{ route('admin.deals.delete', $deal) }}" onsubmit="return confirm('Delete this deal?')">@csrf @method('delete')<button class="button button-ghost danger" type="submit">Delete</button></form></div></article>
        @empty<p class="muted">No deals found.</p>@endforelse</div>

    @elseif($resource === 'bookings')
        <form class="admin-filter-form" method="get" action="{{ route('admin.bookings') }}"><label><span class="visually-hidden">Search bookings</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Search ref, name, email, package…"></label><button class="button button-ghost" type="submit">Search</button></form>
        <nav class="admin-tabs" aria-label="Booking status">@foreach(['All', 'Confirmed', 'Pending payment', 'Cancelled'] as $tab)<a class="{{ (request('status', 'All') === $tab) ? 'is-active' : '' }}" href="{{ route('admin.bookings', array_filter(['status' => $tab === 'All' ? null : $tab, 'q' => request('q')])) }}">{{ $tab }}</a>@endforeach</nav>
        <div class="table-scroll"><table><thead><tr><th>Ref</th><th>Customer + email</th><th>Package</th><th>Departure</th><th>Guests</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($rows as $row)<tr><td>@if($row->created_at?->isToday())<span class="admin-new-dot" aria-label="New"></span>@endif{{ $row->reference }}</td><td>{{ $row->user?->name ?? data_get($row->guest_details, 'name', 'Guest') }}<small>{{ $row->user?->email ?? data_get($row->guest_details, 'email', '—') }}</small></td><td>{{ $row->package?->name ?? '—' }}</td><td>{{ $row->departure_date->format('j M Y') }}</td><td>{{ $row->guests }}</td><td>{{ fmt_rm($row->total) }}</td><td><span class="status-pill">{{ $row->status }}</span>@if($row->status === 'Cancelled' && $row->cancelled_at)<small>Cancelled {{ $row->cancelled_at->format('j M Y, H:i') }}@if($row->cancelledBy) by {{ $row->cancelledBy->name }}@endif</small>@endif</td><td><a class="table-link" href="{{ route('admin.bookings.show', $row) }}">View</a></td></tr>@empty<tr><td colspan="8">No bookings found.</td></tr>@endforelse
        </tbody></table></div>

    @elseif($resource === 'enquiries')
        <div class="admin-panel-heading"><h2>{{ $waitingCount }} waiting for a reply</h2><nav class="admin-tabs" aria-label="Enquiry status">@foreach(['All', 'Unanswered', 'Replied'] as $tab)<a class="{{ request('status', 'All') === $tab ? 'is-active' : '' }}" href="{{ route('admin.enquiries', array_filter(['status' => $tab === 'All' ? null : $tab, 'q' => request('q')])) }}">{{ $tab }}</a>@endforeach</nav></div>
        <form class="admin-filter-form" method="get" action="{{ route('admin.enquiries') }}"><input type="hidden" name="status" value="{{ request('status') }}"><label><span class="visually-hidden">Search enquiries</span><input name="q" type="search" value="{{ request('q') }}" placeholder="Search name, email, subject…"></label><button class="button button-ghost" type="submit">Search</button></form>
        <div class="admin-enquiry-list">@forelse($rows as $row)<article class="admin-enquiry-card"><div class="admin-enquiry-head"><div><span class="status-pill">{{ in_array($row->status, ['New', 'In progress'], true) ? 'Unanswered' : 'Replied' }}</span><h2>{{ $row->subject }}</h2></div><time>{{ $row->created_at->format('j M Y') }}</time></div><p><strong>{{ $row->name }}</strong> · <a href="mailto:{{ $row->email }}">{{ $row->email }}</a></p><p>{{ $row->message }}</p><div class="admin-enquiry-actions"><a class="button button-primary" href="{{ route('admin.enquiries.show', $row) }}">Reply</a>@if(in_array($row->status, ['New', 'In progress'], true))<form method="post" action="{{ route('admin.status', ['resource' => 'enquiries', 'id' => $row->id]) }}">@csrf @method('patch')<input type="hidden" name="status" value="Replied"><button class="button button-ghost" type="submit">Mark replied</button></form>@endif</div>@if($row->status === 'Replied' && $row->latestReply)<p class="admin-enquiry-last-replied">Last replied {{ $row->latestReply->sent_at?->format('j M Y, H:i') }} by {{ $row->latestReply->admin?->name ?? 'Admin' }}</p>@endif</article>@empty<p class="muted">No enquiries found.</p>@endforelse</div>

    @elseif($resource === 'guides')
        <form class="admin-filter-form" method="get" action="{{ route('admin.guides') }}"><label><span class="visually-hidden">Search guides</span><input name="q" type="search" value="{{ request('q') }}" placeholder="Search guides…"></label><button class="button button-ghost" type="submit">Search</button></form>
        <div class="table-scroll"><table><thead><tr><th>Guide</th><th>Topic</th><th>Read time</th><th>Status</th><th></th></tr></thead><tbody>@forelse($rows as $row)<tr><td><div class="table-package">@if($row->image)<img src="{{ asset_img($row->image) }}" alt="">@endif<strong>{{ $row->title }}</strong></div></td><td>{{ $row->category }}</td><td>{{ $row->read_time ?: '—' }}</td><td><span class="status-pill">{{ $row->published ? 'Published' : 'Draft' }}</span></td><td><a class="table-link" href="{{ route('admin.guides.edit', $row) }}">Edit</a></td></tr>@empty<tr><td colspan="5">No guides yet.</td></tr>@endforelse</tbody></table></div>

    @elseif($resource === 'media')
        @php($imageCount = $rows->where('kind', 'image')->count())
        @php($videoCount = $rows->where('kind', 'video')->count())
        <div class="admin-media-toolbar">
            <div>
                <p class="eyebrow accent">Content workspace</p>
                <h2>Your media</h2>
                <p class="muted"><span data-media-image-count>{{ $imageCount }}</span> images · <span data-media-video-count>{{ $videoCount }}</span> videos · <span data-media-bytes>{{ number_format(($mediaBytes ?? 0) / 1024 / 1024, 2) }}</span> MB in public/assets</p>
                <span data-media-total-bytes="{{ $mediaBytes ?? 0 }}" hidden></span>
            </div>
        </div>
        @error('file')<div class="notice notice-error admin-error" role="alert">{{ $message }}</div>@enderror
        <div class="admin-media-progress" data-media-progress hidden><span></span></div>
        <div class="admin-media-workspace">
            <aside class="admin-media-folders" aria-label="Media folders">
                <div class="admin-media-folders-heading">
                    <h2>Folders</h2>
                    <details class="admin-media-folder-create">
                        <summary aria-label="Create folder" title="Create folder">+</summary>
                        <form method="post" action="{{ route('admin.media.folders.create') }}" class="admin-media-menu">
                            @csrf
                            <input type="hidden" name="parent" value="{{ $mediaFolder }}">
                            <label for="media-folder-name">New folder</label>
                            <input id="media-folder-name" name="name" maxlength="80" placeholder="Folder name" required>
                            <button class="button button-primary" type="submit">Create folder</button>
                        </form>
                    </details>
                </div>
                <nav class="admin-media-folder-tree" aria-label="Folder navigation">
                    <a class="admin-media-folder-link {{ $mediaFolder === '' ? 'is-active' : '' }}" href="{{ route('admin.media') }}">
                        <span class="admin-media-folder-icon" aria-hidden="true">▧</span>
                        <span>All media</span>
                        <span class="admin-media-folder-count">{{ $allMediaFiles->count() }}</span>
                    </a>
                    @foreach($allMediaFolders as $folder)
                        @php($folderDepth = substr_count($folder, '/'))
                        <div class="admin-media-folder-row" style="--folder-depth: {{ $folderDepth }}">
                            <a class="admin-media-folder-link {{ $mediaFolder === $folder ? 'is-active' : '' }}" href="{{ route('admin.media', ['folder' => $folder]) }}" title="{{ $folder }}">
                                <span class="admin-media-folder-icon" aria-hidden="true">▰</span>
                                <span>{{ basename($folder) }}</span>
                            </a>
                            <details class="admin-media-folder-options">
                                <summary aria-label="Options for {{ basename($folder) }}" title="Folder options">⋯</summary>
                                <div class="admin-media-menu">
                                    <form method="post" action="{{ route('admin.media.folders.rename') }}">
                                        @csrf
                                        <input type="hidden" name="path" value="{{ $folder }}">
                                        <input type="hidden" name="current_folder" value="{{ $mediaFolder }}">
                                        <label for="rename-folder-{{ $loop->index }}">Rename folder</label>
                                        <input id="rename-folder-{{ $loop->index }}" name="name" value="{{ basename($folder) }}" maxlength="80" required>
                                        <button class="button button-ghost" type="submit">Save name</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.media.folders.move') }}">
                                        @csrf
                                        <input type="hidden" name="path" value="{{ $folder }}">
                                        <input type="hidden" name="current_folder" value="{{ $mediaFolder }}">
                                        <label for="move-folder-{{ $loop->index }}">Move to</label>
                                        <select id="move-folder-{{ $loop->index }}" name="destination">
                                            <option value="">Root folder</option>
                                            @foreach($allMediaFolders as $destination)
                                                <option value="{{ $destination }}" @selected($destination === dirname($folder) && dirname($folder) !== '.') @disabled($destination === $folder || str_starts_with($destination, $folder.'/'))>{{ $destination }}</option>
                                            @endforeach
                                        </select>
                                        <button class="button button-ghost" type="submit">Move folder</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.media.folders.delete') }}" onsubmit="return confirm('Delete this empty folder? Folders with files or subfolders cannot be deleted.')">
                                        @csrf
                                        @method('delete')
                                        <input type="hidden" name="path" value="{{ $folder }}">
                                        <input type="hidden" name="current_folder" value="{{ $mediaFolder }}">
                                        <button class="admin-media-menu-danger" type="submit">Delete empty folder</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    @endforeach
                </nav>
                <form class="admin-media-upload" method="post" action="{{ route('admin.media.upload') }}" enctype="multipart/form-data" data-media-upload>
                    @csrf
                    <input type="hidden" name="folder" value="{{ $mediaFolder !== '' ? $mediaFolder : 'uploads' }}">
                    <label class="button button-primary admin-upload-button">
                        <svg aria-hidden="true" viewBox="0 0 20 20"><path d="M10 14V3m-4 4 4-4 4 4M3 14v3h14v-3"></path></svg>
                        Upload media
                        <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif,video/mp4,video/webm,video/quicktime" required data-media-file>
                    </label>
                    <noscript><button class="button button-ghost" type="submit">Upload</button></noscript>
                    <span class="admin-media-upload-status" data-media-status aria-live="polite"></span>
                </form>
                <p class="admin-media-folder-hint">Choose a folder to browse. Use its menu to rename, move, or delete it.</p>
            </aside>
            <section class="admin-media-browser" aria-label="Media files">
                <div class="admin-media-browser-toolbar">
                    <nav class="admin-media-breadcrumbs" aria-label="Current folder">
                        <a href="{{ route('admin.media') }}">Media</a>
                        @php($breadcrumbPath = '')
                        @foreach(explode('/', $mediaFolder) as $segment)
                            @if($segment !== '')
                                @php($breadcrumbPath = $breadcrumbPath === '' ? $segment : $breadcrumbPath.'/'.$segment)
                                <span aria-hidden="true">/</span>
                                <a href="{{ route('admin.media', ['folder' => $breadcrumbPath]) }}">{{ $segment }}</a>
                            @endif
                        @endforeach
                    </nav>
                    <div class="admin-media-filters">
                        <label class="admin-media-search"><span class="visually-hidden">Search assets</span><input type="search" placeholder="Search in this folder…" data-media-search></label>
                        <div class="admin-media-tabs" role="group" aria-label="Filter assets">
                            <button type="button" class="is-active" data-media-filter="all">All <span>{{ $rows->count() }}</span></button>
                            <button type="button" data-media-filter="image">Images <span>{{ $imageCount }}</span></button>
                            <button type="button" data-media-filter="video">Videos <span>{{ $videoCount }}</span></button>
                        </div>
                    </div>
                </div>
                <div class="admin-media-items-heading">
                    <span><strong data-media-count>{{ $rows->count() }}</strong> {{ $rows->count() === 1 ? 'item' : 'items' }} in this folder</span>
                    <span>{{ $mediaFolder === '' ? 'All folders' : $mediaFolder }}</span>
                </div>
                <span class="admin-media-filter-empty" data-media-empty-filter hidden>No assets match this filter.</span>
                <div class="admin-media-grid" data-media-grid>
                    @forelse($rows as $file)
                        <article class="admin-media-card" data-media-kind="{{ $file['kind'] }}" data-media-name="{{ strtolower($file['name']) }}" data-media-path="{{ $file['path'] }}" data-media-size="{{ $file['size'] }}">
                            <button type="button" class="admin-media-preview" data-media-preview data-media-type="{{ $file['kind'] }}" data-media-url="{{ $file['url'] }}" data-media-name="{{ $file['name'] }}" data-media-path="{{ $file['path'] }}" aria-label="Preview {{ $file['name'] }}">
                                @if($file['kind'] === 'video')
                                    <video src="{{ $file['url'] }}" muted playsinline preload="metadata"></video>
                                    <span class="admin-media-play" aria-hidden="true">▶</span>
                                @else
                                    <img src="{{ $file['url'] }}" alt="" loading="lazy">
                                @endif
                                <span class="admin-media-kind">{{ $file['kind'] }}</span>
                            </button>
                            <div class="admin-media-info">
                                <div class="admin-media-file-meta">
                                    <strong title="{{ $file['name'] }}">{{ $file['name'] }}</strong>
                                    <small>{{ number_format($file['size'] / 1024, 0) }} KB</small>
                                </div>
                                <details class="admin-media-file-options">
                                    <summary aria-label="Options for {{ $file['name'] }}" title="File options">⋯</summary>
                                    <div class="admin-media-menu">
                                        <button class="admin-media-menu-action" type="button" data-copy-asset="{{ $file['path'] }}">Copy path</button>
                                        <a class="admin-media-menu-action" href="{{ $file['url'] }}" target="_blank" rel="noopener">Open file</a>
                                        <form method="post" action="{{ route('admin.media.rename') }}" data-media-rename>
                                            @csrf
                                            <input type="hidden" name="path" value="{{ $file['path'] }}">
                                            <input type="hidden" name="current_folder" value="{{ $mediaFolder }}">
                                            <button class="admin-media-menu-action" type="button" data-media-rename-trigger aria-label="Rename {{ $file['name'] }}">Rename</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.media.move') }}" class="admin-media-move-form">
                                            @csrf
                                            <input type="hidden" name="path" value="{{ $file['path'] }}">
                                            <input type="hidden" name="current_folder" value="{{ $mediaFolder }}">
                                            <label for="move-file-{{ $loop->index }}">Move to</label>
                                            <select id="move-file-{{ $loop->index }}" name="folder" aria-label="Move {{ $file['name'] }} to folder">
                                                <option value="">Root folder</option>
                                                @foreach($allMediaFolders as $destination)
                                                    <option value="{{ $destination }}" @selected($destination === dirname($file['path']) && dirname($file['path']) !== '.')>{{ $destination }}</option>
                                                @endforeach
                                            </select>
                                            <button class="button button-ghost" type="submit">Move file</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.media.delete') }}" data-media-delete>
                                            @csrf
                                            @method('delete')
                                            <input type="hidden" name="path" value="{{ $file['path'] }}">
                                            <button class="admin-media-menu-danger" type="submit" aria-label="Delete {{ $file['name'] }}">Delete file</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </article>
                    @empty
                        <p class="muted admin-media-empty">No media files in this folder. Upload an image or video to get started.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <template data-media-card-template>
            <article class="admin-media-card" data-media-kind="" data-media-name="" data-media-path="">
                <button type="button" class="admin-media-preview" data-media-preview data-media-type="" data-media-url="" data-media-name="" data-media-path="" aria-label="Preview asset">
                    <video muted playsinline preload="metadata" hidden></video>
                    <img alt="" loading="lazy" hidden>
                    <span class="admin-media-play" aria-hidden="true" hidden>▶</span>
                    <span class="admin-media-kind"></span>
                </button>
                <div class="admin-media-info">
                    <div class="admin-media-file-meta"><strong></strong><small></small></div>
                    <details class="admin-media-file-options">
                        <summary aria-label="File options">⋯</summary>
                        <div class="admin-media-menu">
                            <button class="admin-media-menu-action" type="button" data-copy-asset>Copy path</button>
                            <a class="admin-media-menu-action" href="#" target="_blank" rel="noopener">Open file</a>
                            <form method="post" action="{{ route('admin.media.rename') }}" data-media-rename>
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="path" value="">
                                <input type="hidden" name="current_folder" value="{{ $mediaFolder }}">
                                <button class="admin-media-menu-action" type="button" data-media-rename-trigger aria-label="Rename media">Rename</button>
                            </form>
                            <form method="post" action="{{ route('admin.media.move') }}" class="admin-media-move-form">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="path" value="">
                                <input type="hidden" name="current_folder" value="{{ $mediaFolder }}">
                                <label><span class="visually-hidden">Move media to folder</span><select name="folder" aria-label="Move media to folder"><option value="">Root folder</option>@foreach($allMediaFolders as $destination)<option value="{{ $destination }}">{{ $destination }}</option>@endforeach</select></label>
                                <button class="button button-ghost" type="submit">Move file</button>
                            </form>
                            <form method="post" action="{{ route('admin.media.delete') }}" data-media-delete>
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="delete">
                                <input type="hidden" name="path" value="">
                                <button class="admin-media-menu-danger" type="submit" aria-label="Delete media">Delete file</button>
                            </form>
                        </div>
                    </details>
                </div>
            </article>
        </template>
        <div class="admin-media-modal" data-media-modal hidden>
            <div class="admin-media-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-media-modal-title" tabindex="-1">
                <header>
                    <div><span class="eyebrow accent" data-media-modal-kind>Asset</span><h2 id="admin-media-modal-title" data-media-modal-title>Asset preview</h2></div>
                    <button class="admin-media-modal-close" type="button" aria-label="Close preview" data-media-close>×</button>
                </header>
                <div class="admin-media-modal-body" data-media-modal-body></div>
                <footer><code data-media-modal-path></code><button class="button button-ghost" type="button" data-copy-asset data-media-modal-copy>Copy path</button></footer>
            </div>
        </div>

    @elseif($resource === 'users')
        <div class="admin-users-layout"><div><form class="admin-filter-form" method="get" action="{{ route('admin.users') }}"><label><span class="visually-hidden">Search users</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Search users…"></label><button class="button button-ghost" type="submit">Search</button></form>
            <div class="admin-user-list">@forelse($rows as $user)<a class="{{ $selectedUser?->id === $user->id ? 'is-active' : '' }}" href="{{ route('admin.users', array_filter(['user' => $user->id, 'q' => request('q')])) }}"><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></a>@empty<p class="muted">No users found.</p>@endforelse</div>
        </div><aside class="admin-user-detail">@if($selectedUser)<p class="eyebrow accent">Traveller profile</p><h2>{{ $selectedUser->name }}</h2><p>{{ $selectedUser->email }}</p><p>{{ $selectedUser->phone ?: 'No phone number' }}</p><dl><div><dt>Member since</dt><dd>{{ $selectedUser->created_at->format('j M Y') }}</dd></div><div><dt>Last active</dt><dd>{{ $selectedLastActive?->format('j M Y, H:i') ?? '—' }}</dd></div><div><dt>Bookings</dt><dd>{{ $selectedBookings }}</dd></div><div><dt>Confirmed spend</dt><dd>{{ fmt_rm($selectedSpend) }}</dd></div></dl>@else<p class="muted">Choose a traveller to view their details.</p>@endif</aside></div>
    @endif
    @if(method_exists($rows, 'links'))<div class="pagination">{{ $rows->links() }}</div>@endif
</section>
@endsection
