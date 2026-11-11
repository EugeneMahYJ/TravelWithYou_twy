<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $orderReference,
        public Collection $bookings,
        public string $paymentStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your TravelWithYou booking · '.$this->orderReference,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking-confirmation',
        );
    }
}
