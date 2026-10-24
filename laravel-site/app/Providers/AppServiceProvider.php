<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Payments\SimulatedPaymentGateway;
use App\Support\AdminNotifications;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (in_array(config('app.env'), ['local', 'testing'], true)) {
            $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::share('company', config('company'));
        $this->app->scoped(AdminNotifications::class);
        View::composer('admin.layouts.admin', function ($view): void {
            $notifications = app(AdminNotifications::class);
            $view->with([
                'adminAlertCount' => $notifications->countFor('enquiries.unanswered'),
                'adminNotifications' => $notifications->itemsForAdmin(),
                'adminNotificationCount' => $notifications->unreadBadgeCount(),
                'adminNotificationUnreadCount' => $notifications->unreadItemCount(),
            ]);
        });
    }
}
