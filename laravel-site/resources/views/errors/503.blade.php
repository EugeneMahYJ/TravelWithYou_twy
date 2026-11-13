<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Error 503 — Travel With You</title>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>
    {{-- Standalone on purpose: no database, session or layout code, so it still renders when those are what failed. --}}
    <main class="wrap not-found-page" id="main">
    <svg aria-hidden="true" viewBox="0 0 48 48">
        <path d="M4 31h40l-6 9H12l-8-9Z"></path>
        <path d="M24 5v26m0-23 13 11H24M24 13 12 22h12"></path>
        <path d="M5 44c3-2 5-2 8 0s5 2 8 0 5-2 8 0 5 2 8 0 5-2 8 0"></path>
    </svg>
        <p class="eyebrow accent">Error 503</p>
        <h1>We will be right back.</h1>
        <p>Travel With You is being updated. Please check again in a few minutes.</p>
        <div>
            <a class="button button-primary" href="/">Back home</a>
        </div>
    </main>
</body>
</html>
