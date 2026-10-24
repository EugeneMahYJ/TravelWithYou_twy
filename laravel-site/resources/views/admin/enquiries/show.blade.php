@extends('admin.layouts.admin')
@section('title', 'Enquiry · '.$enquiry->subject)
@section('content')
<div class="admin-heading">
    <div><p class="eyebrow accent">Enquiries</p><h1>{{ $enquiry->subject }}</h1><p>Received {{ $enquiry->created_at->format('j M Y, H:i') }}</p></div>
    <a class="button button-ghost" href="{{ route('admin.enquiries') }}">Back to enquiries</a>
</div>
<div class="admin-enquiry-detail-layout">
    <section class="admin-panel admin-enquiry-original">
        <div class="admin-panel-heading"><h2>Original enquiry</h2><span class="status-pill">{{ in_array($enquiry->status, ['New', 'In progress'], true) ? 'Unanswered' : 'Replied' }}</span></div>
        <dl class="admin-enquiry-contact">
            <div><dt>Customer</dt><dd>{{ $enquiry->name }}</dd></div>
            <div><dt>Email</dt><dd>{{ $enquiry->email }}</dd></div>
            @if($enquiry->phone)<div><dt>Phone</dt><dd>{{ $enquiry->phone }}</dd></div>@endif
            <div><dt>Topic</dt><dd>{{ $enquiry->subject }}</dd></div>
            <div><dt>Received</dt><dd>{{ $enquiry->created_at->format('j M Y, H:i') }}</dd></div>
        </dl>
        <div class="admin-enquiry-message">{{ $enquiry->message }}</div>
        @if($enquiry->status === 'Replied' && $latestReply)
            <p class="admin-enquiry-last-replied">Last replied {{ $latestReply->sent_at?->format('j M Y, H:i') }} by {{ $latestReply->admin?->name ?? 'Admin' }}</p>
        @endif
    </section>
    <section class="admin-panel admin-enquiry-conversation">
        <div class="admin-panel-heading"><h2>Conversation history</h2></div>
        @forelse($enquiry->replies as $reply)
            <article class="admin-enquiry-reply admin-enquiry-reply-{{ $reply->status }}">
                <div class="admin-enquiry-reply-heading"><strong>{{ $reply->admin?->name ?? 'Admin' }}</strong><span>{{ $reply->status === 'sent' ? $reply->sent_at?->format('j M Y, H:i') : 'Failed · '.$reply->created_at->format('j M Y, H:i') }}</span></div>
                <h3>{{ $reply->subject }}</h3>
                <p class="admin-enquiry-message">{{ $reply->body }}</p>
                @if($reply->status === 'failed')<p class="admin-enquiry-failed">This reply was not sent.</p>@endif
            </article>
        @empty
            <p class="muted">No replies have been sent yet.</p>
        @endforelse
    </section>
    <section class="admin-panel admin-enquiry-reply-form-panel">
        <div class="admin-panel-heading"><h2>Write a reply</h2></div>
        <form class="admin-editor-form admin-enquiry-reply-form" method="post" action="{{ route('admin.enquiries.reply', $enquiry) }}">
            @csrf
            <label><span>Subject</span><input name="subject" value="{{ old('subject', 'Re: '.$enquiry->subject) }}" maxlength="255" required></label>
            <label><span>Message</span><textarea name="message" rows="8" minlength="5" maxlength="5000" required data-character-count>{{ old('message') }}</textarea><small class="admin-character-count" aria-live="polite"><span data-character-count-value>{{ mb_strlen(old('message', '')) }}</span> / 5000 characters</small></label>
            <div class="admin-form-actions"><button class="button button-primary" type="submit">Send reply</button><a class="button button-ghost" href="{{ route('admin.enquiries') }}">Back to enquiries</a></div>
        </form>
    </section>
</div>
@endsection
