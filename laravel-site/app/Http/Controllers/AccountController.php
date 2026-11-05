<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Support\ClientNotifications;
use App\Support\CredentialRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    private const TABS = ['profile', 'inbox', 'bookings', 'wishlist', 'travellers', 'payments', 'notifications', 'security'];

    public function show(Request $request): View
    {
        $user = $request->user();
        $bookings = $user->bookings()->with('package')->orderByDesc('departure_date')->get();
        $upcomingBookings = $bookings->filter(fn ($booking) => $booking->departure_date->isToday() || $booking->departure_date->isFuture())->values();
        $completedBookings = $bookings->reject(fn ($booking) => $booking->departure_date->isToday() || $booking->departure_date->isFuture())->values();
        $activeTab = $request->query('tab', 'profile');

        return view('account', [
            'user' => $user,
            'bookings' => $bookings,
            'upcomingBookings' => $upcomingBookings,
            'completedBookings' => $completedBookings,
            'enquiries' => $user->enquiries()
                ->with([
                    'replies' => fn ($query) => $query->where('status', 'sent')->with('admin'),
                    'messages.sender',
                ])
                ->orderByDesc('created_at')
                ->get(),
            'userNotifications' => $user->siteNotifications()->latest()->get(),
            'favorites' => $user->favorites()->where('published', true)->orderBy('name')->get(),
            'activeTab' => in_array($activeTab, self::TABS, true) ? $activeTab : 'profile',
            'travellerDetails' => $user->traveller_details ?? [],
            'notificationPreferences' => array_merge([
                'deals' => true,
                'trips' => true,
                'news' => false,
            ], $user->notification_preferences ?? []),
        ]);
    }

    public function replyToEnquiry(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $request->merge(['message' => trim((string) $request->input('message', ''))]);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        DB::transaction(function () use ($enquiry, $request, $data): void {
            $ownedEnquiry = Enquiry::query()
                ->whereKey($enquiry->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            EnquiryMessage::create([
                'enquiry_id' => $ownedEnquiry->id,
                'sender_id' => $request->user()->id,
                'body' => $data['message'],
            ]);
            $ownedEnquiry->update(['status' => 'New']);
        });

        return redirect()->route('account', ['tab' => 'inbox'])
            ->withFragment('account-enquiry-'.$enquiry->id)
            ->with('success', 'Your message was sent to our team.');
    }

    public function cancelBooking(Request $request, ClientNotifications $notifications, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        $cancelled = DB::transaction(function () use ($booking, $request, $notifications): bool {
            $lockedBooking = $booking->newQuery()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($lockedBooking->user_id === $request->user()->id, 403);
            if ($lockedBooking->status !== 'Pending payment') {
                return false;
            }

            $lockedBooking->forceFill([
                'status' => 'Cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
            ])->save();
            $notifications->bookingStatusChanged($lockedBooking);

            return true;
        });

        return back()->with(
            $cancelled ? 'success' : 'error',
            $cancelled ? 'Booking cancelled.' : 'Only payment pending bookings can be cancelled.',
        );
    }

    public function updateProfile(Request $request, ClientNotifications $notifications): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'not_regex:/\s/',
                'regex:/\A[a-zA-Z0-9._-]+\z/',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if ($user->newQuery()->whereRaw('LOWER(username) = ?', [Str::lower($value)])->whereKeyNot($user->id)->exists()) {
                        $fail('That username is already taken.');
                    }
                },
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if ($user->newQuery()->whereRaw('LOWER(email) = ?', [Str::lower($value)])->whereKeyNot($user->id)->exists()) {
                        $fail('The email has already been taken.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:40'],
        ], [
            'username.min' => 'Use 3-20 letters, numbers, dots, dashes or underscores.',
            'username.max' => 'Use 3-20 letters, numbers, dots, dashes or underscores.',
            'username.not_regex' => 'Username cannot contain spaces.',
            'username.regex' => 'Use 3-20 letters, numbers, dots, dashes or underscores.',
        ]);

        $emailChanged = Str::lower($user->email) !== Str::lower($data['email']);
        $user->forceFill([
            'username' => Str::lower($data['username']),
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
        ])->save();
        if ($emailChanged) {
            $notifications->accountSecurityChanged(
                $user,
                'Account email updated',
                'The email address on your account has changed.',
            );
        }

        return redirect()->route('account', ['tab' => 'profile'])->with('success', 'Profile updated');
    }

    public function updateTravellers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ic_number' => ['nullable', 'string', 'max:40'],
            'name_as_per_ic' => ['nullable', 'string', 'max:120'],
            'passport_number' => ['nullable', 'string', 'max:40'],
            'passport_expiry' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'dietary_needs' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->forceFill(['traveller_details' => $data])->save();

        return redirect()->route('account', ['tab' => 'travellers'])->with('success', 'Traveller details saved');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'notification_preferences' => [
                'deals' => $request->boolean('deals'),
                'trips' => $request->boolean('trips'),
                'news' => $request->boolean('news'),
            ],
        ])->save();

        return redirect()->route('account', ['tab' => 'notifications'])->with('success', 'Saved');
    }

    public function changePassword(Request $request, ClientNotifications $notifications): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'confirmed', ...CredentialRules::password()],
        ], CredentialRules::passwordMessages('new_password'));

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['new_password'])])->save();
        $notifications->accountSecurityChanged(
            $user,
            'Password changed',
            'Your account password was changed.',
        );

        return redirect()->route('account', ['tab' => 'security'])->with('success', 'Password changed');
    }

    public function toggleTwoFactor(Request $request, ClientNotifications $notifications): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $user = $request->user();
        $changed = $user->account_mfa_enabled !== $enabled;
        $user->forceFill(['account_mfa_enabled' => $enabled])->save();
        if ($changed) {
            $notifications->accountSecurityChanged(
                $user,
                $enabled ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled',
                $enabled
                    ? 'Two-factor authentication was enabled for your account.'
                    : 'Two-factor authentication was disabled for your account.',
            );
        }

        return redirect()->route('account', ['tab' => 'security'])->with('success', $enabled ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.');
    }

    public function openNotification(Request $request, int $notificationId): RedirectResponse
    {
        $notification = $request->user()->siteNotifications()->findOrFail($notificationId);
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        if (! str_starts_with($notification->url, '/') || str_starts_with($notification->url, '//')) {
            abort(404);
        }

        return redirect($notification->url);
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->siteNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('account', ['tab' => 'inbox'])->with('success', 'All notifications marked as read.');
    }

    public function placeholderAction(Request $request): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:add-payment,logout-devices']]);
        $message = $data['action'] === 'add-payment'
            ? 'Card form would open here'
            : 'Logged out from all devices';

        return redirect()->route('account', ['tab' => $data['action'] === 'add-payment' ? 'payments' : 'security'])
            ->with('success', $message);
    }
}
