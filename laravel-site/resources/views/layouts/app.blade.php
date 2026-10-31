<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Thoughtful small-group journeys, slow escapes and boutique cruises with Travel With You.')">
    <title>@yield('title', 'Travel With You — journeys at your own pace')</title>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <script src="{{ asset('js/site.js') }}" defer></script>
    <script src="{{ asset('js/slideshow.js') }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="TravelWithYou home">
                <img class="brand-logo" src="{{ asset('assets/company-logo.png') }}" width="120" height="53" alt="TravelWithYou">
            </a>
            @php
                $primaryNav = [
                    ['label' => 'Destinations', 'url' => route('destinations'), 'active' => request()->is('destinations*')],
                    ['label' => 'Trip', 'url' => route('search'), 'active' => request()->is('search*') || request()->is('packages*') || request()->is('package/*')],
                    ['label' => 'Deals', 'url' => route('deals'), 'active' => request()->is('deals*')],
                    ['label' => 'Cruise', 'url' => route('ships'), 'active' => request()->is('ships*')],
                    ['label' => 'About', 'url' => route('about'), 'active' => request()->is('about*')],
                    ['label' => 'Contact Us', 'url' => route('contact'), 'active' => request()->is('contact*')],
                ];
            @endphp
            <nav class="main-nav" aria-label="Main navigation">
                @foreach($primaryNav as $link)
                    <a class="{{ $link['active'] ? 'is-active' : '' }}" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
            </nav>
            <form class="header-search" action="{{ route('search') }}" method="get" role="search">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search trips, countries…" aria-label="Search trips, countries">
            </form>
            <div class="header-actions">
                <a class="header-icon" href="{{ route('cart') }}" aria-label="Cart, {{ count(session('cart', [])) }} items">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 4h2l2.2 11.1a2 2 0 0 0 2 1.6h8.5a2 2 0 0 0 1.9-1.4L22 8H6"></path><circle cx="10" cy="20" r="1"></circle><circle cx="18" cy="20" r="1"></circle></svg>
                    @if(count(session('cart', [])) > 0)
                        <span class="cart-count">{{ count(session('cart', [])) }}</span>
                    @endif
                </a>
                @auth
                    <a class="header-account" href="{{ route('account') }}" aria-label="My account" title="My account"><span class="account-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span></a>
                @else
                    <a class="header-account" href="{{ route('login') }}" aria-label="Log in"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path></svg><span>Log in</span></a>
                @endauth
                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Open navigation"><span></span><span></span></button>
            </div>
        </div>
        <nav class="mobile-nav" id="mobile-nav" aria-label="Mobile navigation" hidden>
            <div class="mobile-nav-inner">
                @foreach($primaryNav as $link)
                    <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
                <a href="{{ route('cart') }}">Cart ({{ count(session('cart', [])) }})</a>
                @auth
                    <a href="{{ route('account') }}">My account</a>
                @else
                    <a href="{{ route('login') }}">Log in / Register</a>
                @endauth
            </div>
        </nav>
    </header>

    @if(session('success') || session('error'))
        <div class="toast" data-toast data-auto-dismiss="{{ session('error') ? 'false' : 'true' }}" role="{{ session('error') ? 'alert' : 'status' }}" aria-atomic="true">
            <span>{{ session('error') ?? session('success') }}</span>
            @if(session('error'))
                <button type="button" aria-label="Close message" data-toast-close>×</button>
            @endif
        </div>
    @endif

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        @php
            $company = config('company');
            $paymentLabels = ['visa' => 'VISA', 'mc' => 'MC', 'amex' => 'AMEX', 'paypal' => 'PayPal', 'bank' => 'Bank'];
            $enabledPaymentMethods = array_intersect_key(array_filter(config('payments.methods', [])), $paymentLabels);
        @endphp
        <div class="wrap footer-grid">
            <div class="footer-brand-column">
                <a class="brand-footer" href="{{ route('home') }}" aria-label="TravelWithYou home"><img class="brand-logo" src="{{ asset('assets/company-logo.png') }}" width="142" height="62" alt="TravelWithYou"></a>
                <p>Tour packages and cruises to forty countries, planned by people who have been there.</p>
                <div class="footer-social">
                    <span>Find us on</span>
                    <div>
                        <a href="https://www.instagram.com/travelwityou/" aria-label="Instagram"><img src="{{ asset('assets/instagram.png') }}" width="40" height="40" alt=""></a>
                        <a href="https://www.facebook.com/TravelwizU" aria-label="Facebook"><img src="{{ asset('assets/facebook.png') }}" width="40" height="40" alt=""></a>
                        <a href="https://www.tiktok.com/@travelwithyoupuchong" aria-label="TikTok"><img src="{{ asset('assets/tiktok.png') }}" width="40" height="40" alt=""></a>
                    </div>
                </div>
                @if($company['email'])
                    <a class="footer-email" href="mailto:enquiry@travelwithyou.my">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="1"></rect><path d="m4 7 8 6 8-6"></path></svg>
                        <span>{{ $company['email'] }}</span>
                    </a>
                @endif
            </div>
            <div class="footer-explore-column">
                <h2>Explore</h2>
                <ul>
                    @foreach(array_slice($primaryNav, 0, 4) as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                    <li><a href="{{ route('blog') }}">Travel Guides</a></li>
                </ul>
            </div>
            <div class="footer-about-column">
                <h2>About</h2>
                <ul>
                    @foreach(array_slice($primaryNav, 4) as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="footer-newsletter-column">
                <h2>Newsletter</h2>
                <p>One email a month. Deals and field notes.</p>
                <form method="post" action="{{ route('newsletter.subscribe') }}">
                    @csrf
                    <label class="visually-hidden" for="newsletter-email">Email for newsletter</label>
                    <input id="newsletter-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@email.com" autocomplete="email" required>
                    <button type="submit">Join</button>
                </form>
                @if($enabledPaymentMethods)
                    <div class="payment-badges" aria-label="Accepted payment methods">
                        @foreach($enabledPaymentMethods as $method => $enabled)
                            <span>{{ $paymentLabels[$method] }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="footer-bottom-wrap">
            <div class="wrap footer-bottom">
                <span>Copyright © 2026 Travel With You Sdn Bhd | All rights reserved.</span>
                <nav aria-label="Legal and account links">
                    <a href="{{ route('terms') }}">Terms &amp; Conditions</a>
                    <a href="{{ route('privacy') }}">Privacy Policy</a>
                    <a href="{{ route('faq') }}">FAQ</a>
                </nav>
            </div>
        </div>
    </footer>
</body>
</html>
