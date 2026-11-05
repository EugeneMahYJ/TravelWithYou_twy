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
            <div><dt>Customer</dt><dd>{{ $enquiry->honorific ? $enquiry->honorific.' ' : '' }}{{ $enquiry->name }}</dd></div>
            <div><dt>Email</dt><dd>{{ $enquiry->email }}</dd></div>
            @if($enquiry->phone)<div><dt>Phone</dt><dd>{{ $enquiry->phone }}</dd></div>@endif
            <div><dt>Topic</dt><dd>{{ $enquiry->subject }}</dd></div>
            @if($enquiry->company_name)
                <div><dt>Company</dt><dd>{{ $enquiry->company_name }}</dd></div>
                <div><dt>Travellers</dt><dd>{{ $enquiry->company_travellers }}</dd></div>
                <div><dt>Company address</dt><dd>{!! nl2br(e($enquiry->company_address)) !!}</dd></div>
                <div><dt>Destination</dt><dd>{{ $enquiry->destination }}</dd></div>
                @if($enquiry->budget_from !== null && $enquiry->budget_to !== null)
                    <div><dt>Budget</dt><dd>RM {{ number_format((float) $enquiry->budget_from, 2) }} – RM {{ number_format((float) $enquiry->budget_to, 2) }} @if($enquiry->budget_basis === 'group')for the whole group @elseif($enquiry->budget_basis === 'traveller')per traveller @endif</dd></div>
                @endif
            @endif
            <div><dt>Received</dt><dd>{{ $enquiry->created_at->format('j M Y, H:i') }}</dd></div>
        </dl>
        <div class="admin-enquiry-message">{{ $enquiry->message }}</div>
        @if($enquiry->status === 'Replied' && $latestReply)
            <p class="admin-enquiry-last-replied">Last replied {{ $latestReply->sent_at?->format('j M Y, H:i') }} by {{ $latestReply->admin?->name ?? 'Admin' }}</p>
        @endif
    </section>
    @if($enquiry->user_id)
        <section class="admin-panel admin-enquiry-conversation">
            <div class="admin-panel-heading"><h2>Conversation history</h2></div>
            @forelse($conversationMessages as $message)
                <article class="admin-enquiry-reply admin-enquiry-reply-{{ $message['status'] }} {{ $message['type'] === 'user' ? 'admin-enquiry-reply-user' : '' }}">
                    <div class="admin-enquiry-reply-heading">
                        <strong>{{ $message['author'] }}</strong>
                        <span>{{ $message['status'] === 'sent' ? $message['timestamp']?->format('j M Y, H:i') : 'Failed · '.$message['timestamp']?->format('j M Y, H:i') }}</span>
                    </div>
                    @if($message['subject'])<h3>{{ $message['subject'] }}</h3>@endif
                    <p class="admin-enquiry-message">{{ $message['body'] }}</p>
                    @if($message['status'] === 'failed')<p class="admin-enquiry-failed">This reply was not sent.</p>@endif
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
    @else
        <section class="admin-panel admin-enquiry-reply-form-panel">
            <div class="admin-panel-heading"><h2>Reply by email</h2></div>
            <p>This enquiry was submitted as a guest. Reply by email; it will not appear in an account conversation.</p>
            <div class="admin-form-actions">
                <a class="button button-primary" href="{{ $guestReplyMailto }}">Open email app</a>
                @if(in_array($enquiry->status, ['New', 'In progress'], true))
                    <form method="post" action="{{ route('admin.status', ['resource' => 'enquiries', 'id' => $enquiry->id]) }}">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="status" value="Replied">
                        <button class="button button-ghost" type="submit">Mark replied</button>
                    </form>
                @endif
                <a class="button button-ghost" href="{{ route('admin.enquiries') }}">Back to enquiries</a>
            </div>
        </section>
    @endif
</div>
@endsection
