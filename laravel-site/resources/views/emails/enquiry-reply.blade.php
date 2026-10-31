<!doctype html>
<html lang="en">
<body>
    <p>{!! nl2br(e($replyBody)) !!}</p>
    <blockquote>{!! nl2br(e($originalMessage)) !!}</blockquote>
    <footer>
        <p>{{ config('app.name', 'Travel With You') }}</p>
        @if(!empty($company['phone']))<p>{{ $company['phone'] }}</p>@endif
        @if(!empty($company['email']))<p>{{ $company['email'] }}</p>@endif
        @if(!empty($company['hours']))<p>{!! nl2br(e($company['hours'])) !!}</p>@endif
    </footer>
</body>
</html>
