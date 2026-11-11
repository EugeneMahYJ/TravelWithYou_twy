<?php

namespace App\Http\Controllers;

use App\Models\TravelPackage;
use App\Models\User;
use App\Support\AccountSessions;
use App\Support\CredentialRules;
use App\Support\CustomerTwoFactor;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const TRUSTED_DEVICE_COOKIE = 'admin_trusted_device';

    private const TRUSTED_DEVICE_DAYS = 30;

    public function loginForm(): View
    {
        return view('auth.login', ['adminLogin' => false]);
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function adminLoginForm(Request $request): View|RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if ($admin?->is_admin) {
            return redirect()->route($admin->mfa_enabled ? 'admin.mfa' : 'admin.mfa.setup');
        }

        return view('admin.auth.login', [
            'adminStatusMessage' => $request->query('reason') === 'idle'
                ? 'You were signed out after 15 minutes of inactivity.'
                : $request->session()->get('error'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $adminLogin = $request->routeIs('admin.login.submit');
        if ($adminLogin && ! $request->filled('username') && $request->filled('email')) {
            $request->merge(['username' => $request->input('email')]);
        }
        $identityField = $adminLogin ? 'username' : 'email';
        $credentials = $request->validate([
            $identityField => $adminLogin ? ['required', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identity = Str::lower($credentials[$identityField]);
        $user = User::query()
            ->where(function ($query) use ($identity): void {
                $query->whereRaw('LOWER(email) = ?', [$identity]);
                $query->orWhereRaw('LOWER(username) = ?', [$identity]);
            })
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([$identityField => 'The username or password is incorrect.'])->onlyInput($identityField);
        }

        if ($adminLogin && ! $user->is_admin) {
            return back()->withErrors([$identityField => 'The username or password is incorrect.'])->onlyInput($identityField);
        }

        if (! $adminLogin && $user->account_mfa_enabled) {
            return $this->beginCustomerTwoFactor($request, $user);
        }

        $guard = Auth::guard($adminLogin ? 'admin' : 'web');
        $guard->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($adminLogin) {
            if (! $user->mfa_secret) {
                $user->forceFill(['mfa_secret' => Totp::generateSecret()])->save();
            }
            if ($user->mfa_enabled && $this->hasTrustedAdminDevice($request, $user)) {
                $request->session()->put('admin_mfa_verified', true);
                $request->session()->put('admin_last_activity', time());

                return redirect()->route('admin.dashboard');
            }
            $request->session()->put('admin_mfa_verified', false);
            $request->session()->put('admin_last_activity', time());

            return redirect()->route($user->mfa_enabled ? 'admin.mfa' : 'admin.mfa.setup');
        }

        $this->syncGuestWishlist($request);

        return redirect()->intended(route('account'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', ...CredentialRules::password()],
        ], CredentialRules::passwordMessages('password'));

        $data['email'] = Str::lower($data['email']);
        $user = User::create($data);
        $this->sendVerificationEmail($user);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->syncGuestWishlist($request);

        return redirect()->route('account')->with('success', 'Your account is ready.');
    }

    private function sendVerificationEmail(User $user): void
    {
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('Verification email could not be sent.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function beginCustomerTwoFactor(Request $request, User $user): RedirectResponse
    {
        try {
            CustomerTwoFactor::issue($user);
        } catch (\Throwable $exception) {
            Log::error('Customer sign-in code could not be sent.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return back()
                ->withErrors(['email' => 'We could not send your sign-in code. Please try again in a few minutes.'])
                ->onlyInput('email');
        }

        $request->session()->put('customer_2fa', [
            'user_id' => $user->id,
            'remember' => $request->boolean('remember'),
        ]);
        $request->session()->regenerate();

        return redirect()->route('login.two-factor');
    }

    private function pendingTwoFactorUser(Request $request): ?User
    {
        $pending = $request->session()->get('customer_2fa');
        if (! is_array($pending) || ! isset($pending['user_id'])) {
            return null;
        }

        $user = User::query()->find((int) $pending['user_id']);
        if (! $user || ! $user->account_mfa_enabled) {
            $request->session()->forget('customer_2fa');

            return null;
        }

        return $user;
    }

    public function twoFactorForm(Request $request): View|RedirectResponse
    {
        $user = $this->pendingTwoFactorUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor', [
            'maskedEmail' => CustomerTwoFactor::maskEmail($user->email),
            'minutes' => CustomerTwoFactor::TTL_MINUTES,
        ]);
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->pendingTwoFactorUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $result = CustomerTwoFactor::check($user, $data['code']);

        if ($result === 'locked') {
            $request->session()->forget('customer_2fa');

            return redirect()->route('login')
                ->withErrors(['email' => 'Too many incorrect codes. Please sign in again.']);
        }
        if ($result === 'expired') {
            return back()->withErrors(['code' => 'That code has expired. Request a new one.']);
        }
        if ($result !== 'ok') {
            return back()->withErrors(['code' => 'That code is incorrect. Check the email and try again.']);
        }

        $remember = (bool) ($request->session()->get('customer_2fa')['remember'] ?? false);
        $request->session()->forget('customer_2fa');
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $this->syncGuestWishlist($request);

        return redirect()->intended(route('account'));
    }

    public function resendTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->pendingTwoFactorUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        try {
            CustomerTwoFactor::issue($user);
        } catch (\Throwable $exception) {
            Log::error('Customer sign-in code could not be re-sent.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return back()->withErrors(['code' => 'We could not send a new code. Please try again in a few minutes.']);
        }

        return back()->with('success', 'We sent you a new code.');
    }

    private function syncGuestWishlist(Request $request): void
    {
        $ids = collect($request->session()->get('wishlist', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $user = Auth::guard('web')->user();
        if ($ids && $user) {
            $user->favorites()->syncWithoutDetaching(
                TravelPackage::where('published', true)->whereIn('id', $ids)->pluck('id')
            );
        }

        $request->session()->forget('wishlist');
    }

    public function logout(Request $request): RedirectResponse
    {
        $adminLogout = $request->routeIs('admin.logout');
        Auth::guard($adminLogout ? 'admin' : 'web')->logout();
        if ($adminLogout) {
            $request->session()->forget(['admin_mfa_verified', 'admin_last_activity']);
        }
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route($adminLogout ? 'admin.login' : 'home');
    }

    public function forgotPassword(): View
    {
        return view('auth.forgot');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($data);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', __($status));
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', ...CredentialRules::password()],
        ], CredentialRules::passwordMessages('password'));
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            AccountSessions::endOtherSessions($user);
            AccountSessions::revokeTrustedAdminDevices($user);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __($status))
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    public function adminMfaSetup(): View|RedirectResponse
    {
        $user = Auth::guard('admin')->user();
        abort_unless($user?->is_admin, 403);
        if ($user->mfa_enabled) {
            return redirect()->route('admin.mfa');
        }
        if (! $user->mfa_secret) {
            $user->forceFill(['mfa_secret' => Totp::generateSecret()])->save();
        }

        return view('admin.auth.mfa-setup', [
            'secret' => $user->mfa_secret,
            'uri' => Totp::provisioningUri($user->email, $user->mfa_secret),
        ]);
    }

    public function adminMfaForm(): View|RedirectResponse
    {
        $user = Auth::guard('admin')->user();
        abort_unless($user?->is_admin, 403);
        if (! $user->mfa_enabled) {
            return redirect()->route('admin.mfa.setup');
        }

        return view('admin.auth.mfa');
    }

    public function verifyAdminMfa(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = Auth::guard('admin')->user();
        abort_unless($user?->is_admin && $user->mfa_secret, 403);

        if (! Totp::verify($user->mfa_secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code is incorrect or has expired. Try the current code from your authenticator app.']);
        }

        if (! $user->mfa_enabled) {
            $user->forceFill(['mfa_enabled' => true])->save();
        }
        $request->session()->put('admin_mfa_verified', true);
        $request->session()->put('admin_last_activity', time());

        $response = redirect()->route('admin.dashboard');
        if ($request->boolean('remember_device')) {
            $token = Str::random(64);
            $expiresAt = now()->addDays(self::TRUSTED_DEVICE_DAYS);
            DB::table('trusted_admin_devices')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response->withCookie(cookie(
                self::TRUSTED_DEVICE_COOKIE,
                $token,
                self::TRUSTED_DEVICE_DAYS * 24 * 60,
                '/',
                null,
                $request->isSecure() || (bool) config('session.secure'),
                true,
                false,
                'lax',
            ));
        }

        return $response;
    }

    private function hasTrustedAdminDevice(Request $request, User $user): bool
    {
        $token = $request->cookie(self::TRUSTED_DEVICE_COOKIE);
        if (! is_string($token) || $token === '') {
            return false;
        }

        return DB::table('trusted_admin_devices')
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->exists();
    }
}
