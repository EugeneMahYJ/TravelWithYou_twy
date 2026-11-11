<!doctype html>
<html lang="en">
<body>
    <p>Your sign-in code is:</p>
    <p style="font-size:24px;letter-spacing:4px"><strong>{{ $code }}</strong></p>
    <p>It expires in {{ $minutes }} minutes. If you did not try to sign in, change your password and ignore this email.</p>
    <footer>
        <p>{{ config('app.name', 'Travel With You') }}</p>
    </footer>
</body>
</html>
