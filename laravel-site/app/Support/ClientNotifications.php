<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\EnquiryReply;
use App\Models\TravelDeal;
use App\Models\User;
use App\Models\UserNotification;

class ClientNotifications
{
    public function bookingStatusChanged(Booking $booking): void
    {
        if (! $booking->user_id) {
            return;
        }

        [$type, $title, $message] = match ($booking->status) {
            'Pending payment' => [
                'pending_payment',
                'Payment needed',
                'Booking '.$booking->reference.' is awaiting payment.',
            ],
            'Confirmed' => [
                'payment_updates',
                'Booking confirmed',
                'Booking '.$booking->reference.' is confirmed.',
            ],
            'Paid' => [
                'payment_updates',
                'Payment confirmed',
                'Payment for booking '.$booking->reference.' is confirmed.',
            ],
            'Cancelled' => [
                'booking_changes',
                'Booking update',
                'Booking '.$booking->reference.' has been cancelled.',
            ],
            default => [
                'booking_changes',
                'Booking update',
                'Booking '.$booking->reference.' status: '.$booking->status.'.',
            ],
        };

        $this->create(
            $booking->user,
            $type,
            $title,
            $message,
            route('account', ['tab' => 'bookings'], false).'#booking-'.$booking->id,
        );
    }

    public function enquiryReplySent(Enquiry $enquiry, EnquiryReply $reply): void
    {
        if (! $enquiry->user_id) {
            return;
        }

        $this->create(
            $enquiry->user,
            'enquiry_reply',
            'A reply to your enquiry',
            'Our team replied to your enquiry: '.$reply->subject,
            route('account', ['tab' => 'inbox'], false).'#account-enquiry-'.$enquiry->id,
        );
    }

    public function accountSecurityChanged(User $user, string $title, string $message): void
    {
        $this->create(
            $user,
            'security',
            $title,
            $message,
            route('account', ['tab' => 'security'], false),
        );
    }

    public function paymentFailed(User $user): void
    {
        $this->create(
            $user,
            'payment_updates',
            'Payment failed',
            'Your payment could not be completed. Your cart is saved so you can review the details and try again.',
            route('checkout', [], false),
        );
    }

    public function dealPublished(TravelDeal $deal): void
    {
        $package = $deal->package;
        if (! $deal->active || ! $deal->type || ! $package?->published) {
            return;
        }

        if ($deal->expires_at?->isPast()
            || (! $deal->expires_at && $deal->ends_at && $deal->ends_at->toDateString() < today()->toDateString())) {
            return;
        }

        $users = User::query()
            ->where('is_admin', false)
            ->get(['id', 'notification_preferences']);

        foreach ($users as $user) {
            $preferences = array_merge(['deals' => true], $user->notification_preferences ?? []);
            if (! $preferences['deals']) {
                continue;
            }

            $this->create(
                $user,
                'new_deal',
                'A new deal is available',
                $deal->name.' is now available. Explore the latest offer.',
                route('deals', [], false),
            );
        }
    }

    private function create(User $user, string $type, string $title, string $message, string $url): void
    {
        UserNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }
}
