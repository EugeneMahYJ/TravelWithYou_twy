<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);

        $routeName = (string) $request->route()?->getName();
        if (str_starts_with($routeName, 'admin.mfa')) {
            return $next($request);
        }

        if (! $user->mfa_enabled) {
            return redirect()->route('admin.mfa.setup');
        }
        if (! (bool) $request->session()->get('admin_mfa_verified', false)) {
            return redirect()->route('admin.mfa');
        }

        $lastActivity = (int) $request->session()->get('admin_last_activity', time());
        if (time() - $lastActivity >= 900) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'You were signed out after 15 minutes of inactivity.');
        }
        $request->session()->put('admin_last_activity', time());

        return $next($request);
    }
}
