<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnquiryReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $replySubject;

    public string $replyBody;

    public string $originalMessage;

    public array $company;

    public function __construct(
        string $replySubject,
        string $replyBody,
        string $originalMessage,
        array $company,
    ) {
        $this->replySubject = $replySubject;
        $this->replyBody = $replyBody;
        $this->originalMessage = $originalMessage;
        $this->company = $company;
    }

    public function envelope(): Envelope
    {
        $replyTo = filled($this->company['email'] ?? null)
            ? [new Address($this->company['email'], $this->company['name'] ?? null)]
            : [];

        return new Envelope(
            replyTo: $replyTo,
            subject: $this->replySubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enquiry-reply',
            text: 'emails.enquiry-reply-text',
        );
    }
}
