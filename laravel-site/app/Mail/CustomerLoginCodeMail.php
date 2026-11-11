<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerLoginCodeMail extends Mailable
{
    public function __construct(
        public string $code,
        public int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('app.name', 'Travel With You').' sign-in code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-code',
            text: 'emails.login-code-text',
        );
    }
}
