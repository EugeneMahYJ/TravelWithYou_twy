<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Enquiry extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'phone',
        'company_name',
        'company_travellers',
        'company_address',
        'honorific',
        'destination',
        'budget_basis',
        'budget_from',
        'budget_to',
    ];

    public function replies(): HasMany
    {
        return $this->hasMany(EnquiryReply::class)->orderBy('sent_at')->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EnquiryMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(EnquiryMessage::class)->latestOfMany('created_at');
    }

    public function conversationMessages(bool $includeFailedReplies = false): Collection
    {
        $replies = $this->replies
            ->filter(fn (EnquiryReply $reply): bool => $includeFailedReplies || $reply->status === 'sent')
            ->map(fn (EnquiryReply $reply): array => [
                'id' => 'reply-'.$reply->id,
                'type' => 'admin',
                'author' => $reply->admin?->name ?? 'Travel With You',
                'subject' => $reply->subject,
                'body' => $reply->body,
                'status' => $reply->status,
                'timestamp' => $reply->sent_at ?? $reply->created_at,
            ]);

        $messages = $this->messages->map(fn (EnquiryMessage $message): array => [
            'id' => 'message-'.$message->id,
            'type' => 'user',
            'author' => $message->sender?->name ?? $this->name,
            'subject' => null,
            'body' => $message->body,
            'status' => 'sent',
            'timestamp' => $message->created_at,
        ]);

        return $replies->concat($messages)
            ->sortBy(fn (array $message) => $message['timestamp']?->getTimestamp() ?? 0)
            ->values();
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(EnquiryReply::class)->where('status', 'sent')->latestOfMany('sent_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
