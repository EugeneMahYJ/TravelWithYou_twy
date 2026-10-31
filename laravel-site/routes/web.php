<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminShipController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TravelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TravelController::class, 'home'])->name('home');
Route::get('/destinations', [TravelController::class, 'destinations'])->name('destinations');
Route::get('/destinations/region/{region}', [TravelController::class, 'destinations'])->name('destinations.region');
Route::get('/destinations/{country}', [TravelController::class, 'country'])->name('destinations.country');
Route::get('/packages', fn () => redirect()->route('search', request()->query(), 301))->name('packages.index');
Route::get('/package/{package:slug}/pdf', [TravelController::class, 'packagePdf'])->name('packages.pdf');
Route::get('/package/{package:slug}', [TravelController::class, 'package'])->name('packages.show');
Route::get('/book/{id}/{date}', [TravelController::class, 'bookingForm'])->name('booking');
Route::post('/book/{id}/{date}', [TravelController::class, 'submitBooking'])->middleware('throttle:10,1')->name('booking.submit');
Route::post('/package/{package}/cart', [TravelController::class, 'addToCart'])->name('cart.add');
Route::post('/package/{package}/wishlist', [TravelController::class, 'toggleWishlist'])->name('wishlist.toggle');
Route::get('/search', [TravelController::class, 'packages'])->name('search');
Route::post('/newsletter', [TravelController::class, 'subscribeNewsletter'])->middleware('throttle:6,1')->name('newsletter.subscribe');
Route::get('/deals', [TravelController::class, 'deals'])->name('deals');
Route::get('/ships', [TravelController::class, 'ships'])->name('ships');
Route::get('/ships/{ship}', [TravelController::class, 'ship'])->name('ships.show');
Route::get('/cart', [TravelController::class, 'cart'])->name('cart');
Route::patch('/cart/{uid}', [TravelController::class, 'updateCartItem'])->name('cart.update');
Route::delete('/cart/{uid}', [TravelController::class, 'removeFromCart'])->name('cart.remove');
Route::post('/cart/promo', [TravelController::class, 'applyPromo'])->name('cart.promo');
Route::get('/checkout', [TravelController::class, 'checkout'])->name('checkout');
Route::post('/checkout', [TravelController::class, 'placeOrder'])->name('checkout.place');
Route::get('/confirmation', [TravelController::class, 'confirmation'])->name('confirmation');
Route::get('/confirmation/invoice/{reference}', [TravelController::class, 'invoice'])->name('confirmation.invoice');
Route::get('/payment-failed', [TravelController::class, 'paymentFailed'])->name('payment.failed');

Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.submit');
Route::get('/register', fn () => view('auth.register'))->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/forgot', [AuthController::class, 'forgotPassword'])->name('password.request');
Route::post('/forgot', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
Route::post('/reset', [AuthController::class, 'resetPassword'])->name('password.update');
Route::get('/account', [AccountController::class, 'show'])->middleware('auth')->name('account');
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/travellers', [AccountController::class, 'updateTravellers'])->name('travellers.update');
    Route::put('/notifications', [AccountController::class, 'updateNotifications'])->name('notifications.update');
    Route::put('/password', [AccountController::class, 'changePassword'])->name('password.update');
    Route::put('/two-factor', [AccountController::class, 'toggleTwoFactor'])->name('two-factor.update');
    Route::post('/bookings/{booking}/cancel', [AccountController::class, 'cancelBooking'])->name('bookings.cancel');
    Route::post('/action', [AccountController::class, 'placeholderAction'])->name('action');
});

Route::get('/about', [TravelController::class, 'about'])->name('about');
Route::get('/contact', [TravelController::class, 'contact'])->name('contact');
Route::post('/contact', [TravelController::class, 'sendEnquiry'])->name('contact.send');
Route::get('/faq', [TravelController::class, 'faq'])->name('faq');
Route::get('/terms', [TravelController::class, 'policy'])->defaults('policy', 'terms')->name('terms');
Route::get('/privacy', [TravelController::class, 'policy'])->defaults('policy', 'privacy')->name('privacy');
Route::get('/blog', [TravelController::class, 'blog'])->name('blog');
Route::get('/blog/{slug}', [TravelController::class, 'blogPost'])->name('blog.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'adminLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->defaults('admin', true)->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/mfa/setup', [AuthController::class, 'adminMfaSetup'])->name('mfa.setup');
        Route::post('/mfa/setup', [AuthController::class, 'verifyAdminMfa'])->middleware('throttle:6,1')->name('mfa.setup.verify');
        Route::get('/mfa', [AuthController::class, 'adminMfaForm'])->name('mfa');
        Route::post('/mfa', [AuthController::class, 'verifyAdminMfa'])->middleware('throttle:6,1')->name('mfa.verify');
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/search', [AdminController::class, 'search'])->name('search');
        Route::post('/activity', [AdminController::class, 'activity'])->name('activity');
        Route::post('/notifications/read-all', [AdminController::class, 'markAllNotificationsRead'])
            ->middleware('throttle:60,1')
            ->name('notifications.read-all');
        Route::post('/notifications/{key}/read', [AdminController::class, 'markNotificationRead'])
            ->where('key', '[a-z0-9_.]+')
            ->middleware('throttle:60,1')
            ->name('notifications.read');
        Route::get('/packages', [AdminController::class, 'packages'])->name('packages');
        Route::get('/packages/create', [AdminController::class, 'createPackage'])->name('packages.create');
        Route::post('/packages', [AdminController::class, 'savePackage'])->name('packages.store');
        Route::get('/packages/{package}/edit', [AdminController::class, 'editPackage'])->name('packages.edit');
        Route::put('/packages/{package}', [AdminController::class, 'savePackage'])->name('packages.update');
        Route::delete('/packages/{package}', [AdminController::class, 'deletePackage'])->name('packages.delete');

        Route::get('/ships', [AdminShipController::class, 'index'])->name('ships');
        Route::get('/ships/create', [AdminShipController::class, 'create'])->name('ships.create');
        Route::post('/ships', [AdminShipController::class, 'store'])->name('ships.store');
        Route::get('/ships/{ship}/edit', [AdminShipController::class, 'edit'])->name('ships.edit');
        Route::put('/ships/{ship}', [AdminShipController::class, 'update'])->name('ships.update');
        Route::delete('/ships/{ship}', [AdminShipController::class, 'destroy'])->name('ships.delete');

        Route::get('/deals/create', [AdminController::class, 'createDeal'])->name('deals.create');
        Route::get('/deals/{deal}/edit', [AdminController::class, 'editDeal'])->name('deals.edit');
        Route::post('/deals', [AdminController::class, 'storeDeal'])->name('deals.store');
        Route::match(['put', 'patch'], '/deals/{deal}', [AdminController::class, 'updateDeal'])->name('deals.update');
        Route::patch('/deals/{deal}/toggle', [AdminController::class, 'toggleDeal'])->name('deals.toggle');
        Route::delete('/deals/{deal}', [AdminController::class, 'deleteDeal'])->name('deals.delete');
        Route::get('/promos/create', [AdminController::class, 'createPromoCode'])->name('promos.create');
        Route::post('/promos', [AdminController::class, 'storePromoCode'])->name('promos.store');
        Route::get('/promos/{promoCode}/edit', [AdminController::class, 'editPromoCode'])->name('promos.edit');
        Route::put('/promos/{promoCode}', [AdminController::class, 'updatePromoCode'])->name('promos.update');
        Route::patch('/promos/{promoCode}/toggle', [AdminController::class, 'togglePromoCode'])->name('promos.toggle');
        Route::delete('/promos/{promoCode}', [AdminController::class, 'deletePromoCode'])->name('promos.delete');
        Route::get('/enquiries/{enquiry}', [AdminController::class, 'showEnquiry'])->name('enquiries.show');
        Route::post('/enquiries/{enquiry}/reply', [AdminController::class, 'replyToEnquiry'])
            ->middleware('throttle:10,1')
            ->name('enquiries.reply');
        Route::get('/bookings/{booking}', [AdminController::class, 'showBooking'])->name('bookings.show');
        Route::get('/guides/create', [AdminController::class, 'createGuide'])->name('guides.create');
        Route::post('/guides', [AdminController::class, 'storeGuide'])->name('guides.store');
        Route::get('/guides/{guide}/edit', [AdminController::class, 'editGuide'])->name('guides.edit');
        Route::put('/guides/{guide}', [AdminController::class, 'updateGuide'])->name('guides.update');
        Route::post('/media', [AdminController::class, 'uploadMedia'])->name('media.upload');
        Route::post('/media/rename', [AdminController::class, 'renameMedia'])->name('media.rename');
        Route::post('/media/move', [AdminController::class, 'moveMedia'])->name('media.move');
        Route::delete('/media', [AdminController::class, 'deleteMedia'])->name('media.delete');
        Route::post('/media/folders', [AdminController::class, 'createMediaFolder'])->name('media.folders.create');
        Route::post('/media/folders/rename', [AdminController::class, 'renameMediaFolder'])->name('media.folders.rename');
        Route::post('/media/folders/move', [AdminController::class, 'moveMediaFolder'])->name('media.folders.move');
        Route::delete('/media/folders', [AdminController::class, 'deleteMediaFolder'])->name('media.folders.delete');
        foreach (['deals', 'bookings', 'enquiries', 'guides', 'media', 'users', 'company'] as $section) {
            Route::get('/'.$section, [AdminController::class, 'resource'])->defaults('resource', $section)->name($section);
        }
        Route::patch('/{resource}/{id}/status', [AdminController::class, 'updateStatus'])
            ->whereIn('resource', ['bookings', 'enquiries'])
            ->name('status');
    });
});
