@props(['title', 'sub'])

<section class="auth-layout">
    <div class="auth-visual">
        <img src="{{ asset_img('1585793753011-397e6e4668d6') }}" alt="" aria-hidden="true">
        <div class="auth-visual-shade"></div>
        <p>The world is better when you&apos;re in it.</p>
    </div>
    <div class="auth-content">
        <div class="auth-content-inner">
            <h1>{{ $title }}</h1>
            <p class="auth-subtitle">{{ $sub }}</p>
            <div class="auth-form-content">{{ $slot }}</div>
        </div>
    </div>
</section>
