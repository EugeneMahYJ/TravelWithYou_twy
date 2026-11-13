<?php

use App\Http\Middleware\EnsureAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            $tokenMismatch = $exception instanceof TokenMismatchException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 419);

            if (! $request->is('admin', 'admin/*')) {
                if (! $tokenMismatch || $request->expectsJson()) {
                    return null;
                }

                // Public form posted after its session expired: go back to the form with a
                // friendly message. Booking, checkout and traveller forms never get their
                // input restored, and passwords, card details and codes are never restored.
                $redirect = back()->with('error', 'Your session expired. Please try again.');
                if (! $request->is('checkout', 'book/*', 'account/travellers')) {
                    $redirect->withInput(collect($request->input())->except([
                        '_token', 'password', 'password_confirmation', 'current_password', 'new_password',
                        'new_password_confirmation', 'code', 'card_number', 'card_expiry', 'card_cvc',
                        'card_name', 'payment_token',
                    ])->all());
                }

                return $redirect;
            }

            if ($tokenMismatch) {
                Auth::guard('admin')->logout();
                if ($request->hasSession()) {
                    $request->session()->forget(['admin_mfa_verified', 'admin_last_activity']);
                    $request->session()->regenerate();
                    $request->session()->regenerateToken();
                }

                return redirect()->route('admin.login')->with('error', 'Your session expired. Please sign in again.');
            }

            if (! $exception instanceof HttpExceptionInterface) {
                return null;
            }

            $status = $exception->getStatusCode();
            [$title, $message] = match ($status) {
                403 => ['Access denied', 'You do not have permission to view this page.'],
                404 => ['Page not found', 'The requested admin page could not be found.'],
                500 => ['Something went wrong', 'The admin page could not be loaded. Please try again.'],
                default => $status >= 500
                    ? ['Something went wrong', 'The admin page could not be loaded. Please try again.']
                    : ['Page not found', 'The requested admin page could not be found.'],
            };

            return response()->view('admin.errors.generic', compact('title', 'message', 'status'), $status);
        });
    })->create();
