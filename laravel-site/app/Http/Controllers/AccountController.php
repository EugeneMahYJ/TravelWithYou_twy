<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Support\CredentialRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    private const TABS = ['profile', 'bookings', 'wishlist', 'travellers', 'payments', 'notifications', 'support', 'security'];

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

    public function cancelBooking(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        $cancelled = DB::transaction(function () use ($booking, $request): bool {
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

            return true;
        });

        return back()->with(
            $cancelled ? 'success' : 'error',
            $cancelled ? 'Booking cancelled.' : 'Only payment pending bookings can be cancelled.',
        );
    }

    public function updateProfile(Request $request): RedirectResponse
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

        $user->forceFill([
            'username' => Str::lower($data['username']),
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
        ])->save();

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

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'confirmed', ...CredentialRules::password()],
        ], CredentialRules::passwordMessages('new_password'));

        $request->user()->forceFill(['password' => Hash::make($data['new_password'])])->save();

        return redirect()->route('account', ['tab' => 'security'])->with('success', 'Password changed');
    }

    public function toggleTwoFactor(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $request->user()->forceFill(['account_mfa_enabled' => $enabled])->save();

        return redirect()->route('account', ['tab' => 'security'])->with('success', $enabled ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.');
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
