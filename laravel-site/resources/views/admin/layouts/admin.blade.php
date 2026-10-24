<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Travel With You Admin</title>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <script src="{{ asset('js/admin.js') }}" defer></script>
    @if(request()->routeIs('admin.media'))<script src="{{ asset('js/admin-media.js') }}" defer></script>@endif
    @if(request()->routeIs('admin.packages.create', 'admin.packages.edit', 'admin.ships.create', 'admin.ships.edit', 'admin.guides.create', 'admin.guides.edit'))<script src="{{ asset('js/admin-asset-picker.js') }}" defer></script>@endif
    @if(request()->routeIs('admin.packages.create', 'admin.packages.edit'))<script src="{{ asset('js/admin-package-form.js') }}" defer></script>@endif
    @if(request()->routeIs('admin.ships.create', 'admin.ships.edit'))<script src="{{ asset('js/admin-ship-form.js') }}" defer></script>@endif
</head>
<body class="admin-body" data-activity-url="{{ route('admin.activity') }}" data-login-url="{{ route('admin.login') }}" data-idle-login-url="{{ route('admin.login', ['reason' => 'idle']) }}">
    <a class="skip-link" href="#admin-main">Skip to content</a>
    <div class="admin-drawer-overlay" id="admin-drawer-overlay" hidden></div>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
        <a class="brand brand-admin" href="{{ route('admin.dashboard') }}"><img class="brand-logo" src="{{ asset('assets/company-logo.png') }}" alt="Travel With You"></a>
        <p class="admin-label">Workspace</p>
        <nav aria-label="Administration">
            <a class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}"><span aria-hidden="true">▦</span>Dashboard</a>
            <a class="{{ request()->routeIs('admin.packages*') ? 'is-active' : '' }}" href="{{ route('admin.packages') }}"><span aria-hidden="true">◇</span>Package</a>
            <a class="{{ request()->routeIs('admin.ships*') ? 'is-active' : '' }}" href="{{ route('admin.ships') }}"><span aria-hidden="true">⚓︎</span>Ships</a>
            <a class="{{ request()->routeIs('admin.deals*') ? 'is-active' : '' }}" href="{{ route('admin.deals') }}"><span aria-hidden="true">％</span>Deals</a>
            <a class="{{ request()->routeIs('admin.media*') ? 'is-active' : '' }}" href="{{ route('admin.media') }}"><span aria-hidden="true">▧</span>Media</a>
            <a class="{{ request()->routeIs('admin.bookings*') ? 'is-active' : '' }}" href="{{ route('admin.bookings') }}"><span aria-hidden="true">▤</span>Bookings</a>
            <a class="{{ request()->routeIs('admin.enquiries*') ? 'is-active' : '' }}" href="{{ route('admin.enquiries') }}"><span aria-hidden="true">✉</span>Enquiries @if($adminAlertCount)<span class="admin-nav-badge">{{ $adminAlertCount }}</span>@endif</a>
            <a class="{{ request()->routeIs('admin.guides*') ? 'is-active' : '' }}" href="{{ route('admin.guides') }}"><span aria-hidden="true">▥</span>Guides</a>
            <a class="{{ request()->routeIs('admin.users*') ? 'is-active' : '' }}" href="{{ route('admin.users') }}"><span aria-hidden="true">♙</span>Users</a>
        </nav>
        <div class="admin-sidebar-bottom">
            <a class="{{ request()->routeIs('admin.company') ? 'is-active' : '' }}" href="{{ route('admin.company') }}">⚙ Settings · Company profile</a>
        </div>
    </aside>
    <div class="admin-content">
        <header class="admin-topbar">
            <button class="admin-menu-button" type="button" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation">☰</button>
            <form class="admin-search" method="get" action="{{ route('admin.search') }}">
                <label class="visually-hidden" for="admin-global-search">Search bookings, packages, ships…</label>
                <input id="admin-global-search" name="q" type="search" placeholder="Search bookings, packages, ships…" value="{{ request()->routeIs('admin.search') ? request('q') : '' }}" required>
            </form>
            <div class="admin-top-actions">
                <div class="admin-notifications">
                    <button class="admin-notifications-toggle" type="button" aria-label="{{ $adminNotificationCount ? 'Notifications, '.$adminNotificationCount.' need attention' : ($adminNotificationUnreadCount ? 'Notifications, '.$adminNotificationUnreadCount.' unread' : 'Notifications, none unread') }}" aria-haspopup="true" aria-expanded="false" aria-controls="admin-notifications-panel">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path></svg>
                        @if($adminNotificationCount)<span class="admin-notification-badge" aria-hidden="true">{{ $adminNotificationCount > 99 ? '99+' : $adminNotificationCount }}</span>@endif
                    </button>
                    <div class="admin-notification-panel" id="admin-notifications-panel" role="region" aria-label="Notifications" tabindex="-1" hidden>
                        @php($hasUnreadNotifications = collect($adminNotifications)->contains(fn (array $item): bool => ! $item['is_read']))
                        <div class="admin-notification-panel-heading">
                            <div>
                                <strong id="admin-notification-panel-heading" tabindex="-1">Notifications</strong>
                                <span data-notification-summary>{{ $adminNotificationCount ? $adminNotificationCount.' need attention' : 'No unread notifications' }}</span>
                            </div>
                            <form method="post" action="{{ route('admin.notifications.read-all') }}" data-notifications-read-all>
                                @csrf
                                @foreach($adminNotifications as $index => $notification)
                                    <input type="hidden" name="items[{{ $index }}][key]" value="{{ $notification['key'] }}">
                                    <input type="hidden" name="items[{{ $index }}][fingerprint]" value="{{ $notification['fingerprint'] }}">
                                @endforeach
                                <button class="admin-notification-action" type="submit" @disabled(! $hasUnreadNotifications)>Mark all as read</button>
                            </form>
                        </div>
                        <div class="admin-notification-rows">
                        @forelse($adminNotifications as $notification)
                            <article class="admin-notification-row admin-notification-{{ $notification['severity'] }} {{ $notification['is_read'] ? 'is-read' : 'is-unread' }}"
                                data-notification-row data-key="{{ $notification['key'] }}" data-fingerprint="{{ $notification['fingerprint'] }}"
                                data-read="{{ $notification['is_read'] ? 'true' : 'false' }}" data-order="{{ $index }}"
                                data-severity="{{ $notification['severity'] }}" data-count="{{ $notification['count'] }}"
                                data-title="{{ $notification['title'] }}" data-read-url="{{ route('admin.notifications.read', ['key' => $notification['key']]) }}">
                                <span class="admin-notification-unread-indicator" aria-hidden="true"></span>
                                <span class="visually-hidden" data-unread-label @if($notification['is_read']) hidden @endif>Unread</span>
                                <a class="admin-notification-item" href="{{ $notification['url'] }}">
                                    <span class="admin-notification-icon" aria-hidden="true">{{ $notification['severity'] === 'critical' ? '!' : ($notification['severity'] === 'warning' ? '•' : 'i') }}</span>
                                    <span class="admin-notification-copy"><strong>{{ $notification['title'] }}</strong><small>{{ $notification['detail'] }}</small>@if($notification['time'])<time>{{ $notification['time'] }}</time>@endif</span>
                                    <span class="admin-notification-count">{{ $notification['count'] }}</span>
                                </a>
                                <div class="admin-notification-row-action" data-notification-action>
                                    @if(! $notification['is_read'])
                                        <form method="post" action="{{ route('admin.notifications.read', ['key' => $notification['key']]) }}" data-notification-read>
                                            @csrf
                                            <input type="hidden" name="fingerprint" value="{{ $notification['fingerprint'] }}">
                                            <button class="admin-notification-action" type="submit" aria-label="Mark as read: {{ $notification['title'] }}">Mark as read</button>
                                        </form>
                                    @else
                                        <span class="admin-notification-read-label">Read</span>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="admin-notification-empty">You're all caught up.</p>
                        @endforelse
                        </div>
                        <p class="visually-hidden" data-notification-announcement aria-live="polite"></p>
                    </div>
                </div>
                <form id="admin-logout-form" method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit">Log Out</button></form>
            </div>
        </header>
        @if(session('success'))<div class="admin-toast" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="notice notice-error admin-error" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="notice notice-error admin-error" role="alert">{{ $errors->first() }}</div>@endif
        <main id="admin-main" class="admin-main">@yield('content')</main>
    </div>
    <div class="idle-modal" id="idle-modal" role="alertdialog" aria-modal="true" aria-labelledby="idle-title" aria-describedby="idle-description" hidden>
        <div class="idle-dialog"><h2 id="idle-title">Still there?</h2><p id="idle-description">You will be signed out in <strong id="idle-countdown">60</strong> seconds due to inactivity.</p><div><button class="button button-primary admin-stay" type="button">Stay signed in</button><button class="button button-ghost" type="submit" form="admin-logout-form">Log out now</button></div></div>
    </div>
</body>
</html>
