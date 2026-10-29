<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAdminDealRequest;
use App\Http\Requests\SaveAdminPackageRequest;
use App\Http\Requests\SaveAdminPromoCodeRequest;
use App\Mail\EnquiryReplyMail;
use App\Models\AdminNotificationRead;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Enquiry;
use App\Models\EnquiryReply;
use App\Models\Guide;
use App\Models\PromoCode;
use App\Models\SearchLog;
use App\Models\Ship;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\AdminNotifications;
use App\Support\AssetLibrary;
use App\Support\Pricing;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(Request $request, AdminNotifications $notifications): View
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = filled($filters['month'] ?? null)
            ? Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth()
            : now()->startOfMonth();
        $months = collect(range(5, 0))->map(fn (int $offset) => $month->copy()->subMonths($offset)->startOfMonth());
        $sales = $months->map(fn (Carbon $date) => [
            'date' => $date,
            'amount' => (float) Booking::query()->where('status', 'Confirmed')
                ->whereBetween('created_at', [$date, $date->copy()->endOfMonth()])->sum('total'),
        ]);
        $previousSales = $sales->get(4)['amount'] ?? 0;
        $selectedSales = $sales->last()['amount'] ?? 0;
        $packages = TravelPackage::query()->where('published', true)->get();
        $expiry = $this->expiryCounts($packages);
        $recentBookings = Booking::with(['package', 'user'])->latest()->limit(6)->get();

        return view('admin.dashboard', [
            'selectedMonth' => $month,
            'months' => $months,
            'sales' => $sales,
            'salesChange' => $previousSales > 0 ? round((($selectedSales - $previousSales) / $previousSales) * 100) : null,
            'userTotal' => User::count(),
            'newUsers' => User::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count(),
            'bookingTotal' => Booking::count(),
            'newBookings' => Booking::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count(),
            'expiry' => $expiry,
            'trending' => SearchLog::query()->whereNotNull('query')->where('query', '!=', '')
                ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->selectRaw('query, COUNT(*) as searches')->groupBy('query')->orderByDesc('searches')->limit(5)->get(),
            'recentBookings' => $recentBookings,
            'attention' => $notifications->items(),
        ]);
    }

    public function activity(Request $request): RedirectResponse
    {
        $request->session()->put('admin_last_activity', time());

        return back();
    }

    public function markNotificationRead(Request $request, string $key, AdminNotifications $notifications): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($key, AdminNotifications::knownKeys(), true), 404);
        $data = $request->validate([
            'fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{40}\z/'],
        ]);

        AdminNotificationRead::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => $key],
            ['fingerprint' => $data['fingerprint'], 'read_at' => now()],
        );
        $notifications->forgetCachedData();

        return $this->notificationReadResponse($request, $notifications, 'Notification marked as read.');
    }

    public function markAllNotificationsRead(Request $request, AdminNotifications $notifications): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:8'],
            'items.*.key' => ['required', 'string', 'max:80'],
            'items.*.fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{40}\z/'],
        ]);
        foreach ($data['items'] as $item) {
            abort_unless(in_array($item['key'], AdminNotifications::knownKeys(), true), 404);
            AdminNotificationRead::updateOrCreate(
                ['user_id' => $request->user()->id, 'key' => $item['key']],
                ['fingerprint' => $item['fingerprint'], 'read_at' => now()],
            );
        }
        $notifications->forgetCachedData();

        return $this->notificationReadResponse($request, $notifications, 'All notifications marked as read.');
    }

    private function notificationReadResponse(
        Request $request,
        AdminNotifications $notifications,
        string $message,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'unread_badge' => $notifications->unreadBadgeCount($request->user()->id),
                'items' => collect($notifications->itemsForAdmin($request->user()->id))
                    ->map(fn (array $item): array => ['key' => $item['key'], 'is_read' => $item['is_read']])
                    ->values(),
            ]);
        }

        return back()->with('success', $message);
    }

    public function search(Request $request): View
    {
        $request->validate(['q' => ['required', 'string', 'max:160']]);
        $term = '%'.$request->string('q')->toString().'%';
        $packages = TravelPackage::where(fn ($query) => $query->where('name', 'like', $term)
            ->orWhere('country', 'like', $term)->orWhere('region', 'like', $term))->limit(20)->get();
        $bookings = Booking::with(['package', 'user'])->where(fn ($query) => $query->where('reference', 'like', $term)
            ->orWhere('order_reference', 'like', $term)
            ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term))
            ->orWhereHas('package', fn ($package) => $package->where('name', 'like', $term)))->latest()->limit(20)->get();
        $ships = Ship::withCount('cabins')->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('class_name', 'like', $term))
            ->orderBy('name')->limit(20)->get();

        return view('admin.search', compact('packages', 'bookings', 'ships'));
    }

    public function packages(Request $request): View
    {
        $query = TravelPackage::query()->orderBy('name');
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $term)
                ->orWhere('country', 'like', $term)->orWhere('region', 'like', $term));
        }
        foreach (['region', 'country'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        $rows = $query->get()->filter(function (TravelPackage $package) use ($request): bool {
            $status = $this->packageStatus($package);
            $package->setAttribute('admin_status', $status);

            return ! $request->filled('status') || $request->query('status') === 'All' || $status === $request->query('status');
        })->values();

        return view('admin.packages.index', [
            'packages' => $rows,
            'regions' => TravelPackage::query()->distinct()->orderBy('region')->pluck('region'),
            'countries' => TravelPackage::query()->distinct()->orderBy('country')->pluck('country'),
            'filters' => $request->only(['q', 'region', 'country', 'status']),
        ]);
    }

    public function createPackage(): View
    {
        $package = new TravelPackage([
            'departures' => [],
            'highlights' => [],
            'trip_plan' => [],
            'pricing' => [
                'guest_types' => Pricing::GUEST_KINDS,
                'departure_prices' => [],
                'cabin_prices' => [],
                'addons' => Pricing::ADDONS,
            ],
        ]);
        $package->setAttribute('admin_status', 'Draft');

        return view('admin.packages.form', [
            'package' => $package,
            'countries' => Country::orderBy('name')->get(),
            'ships' => Ship::with('cabins')->orderBy('name')->get(),
            'mediaAssets' => AssetLibrary::all(),
            'mediaFolders' => AssetLibrary::folders(),
        ]);
    }

    public function editPackage(TravelPackage $package): View
    {
        $package->setAttribute('admin_status', $this->packageStatus($package));

        return view('admin.packages.form', [
            'package' => $package,
            'countries' => Country::orderBy('name')->get(),
            'ships' => Ship::with('cabins')->orderBy('name')->get(),
            'mediaAssets' => AssetLibrary::all(),
            'mediaFolders' => AssetLibrary::folders(),
        ]);
    }

    public function savePackage(SaveAdminPackageRequest $request, ?TravelPackage $package = null): RedirectResponse
    {
        $data = $request->validated();
        $builder = $request->boolean('builder_form');
        $existing = $package;
        $draft = $builder && $data['status'] === 'Draft';
        $departures = $builder ? ($data['departures'] ?? []) : preg_split('/\r\n|\r|\n/', $data['departures_text'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $tripPlan = $builder
            ? collect($data['trip_plan'] ?? [])->map(fn (array $day) => [
                $day['place'] ?? '',
                $day['activities'] ?? '',
                $day['transport'] ?? '',
            ])->values()->all()
            : ($existing?->trip_plan ?? []);
        $slug = $data['slug'] ?: ($existing?->slug ?: Str::slug($data['name']));
        $sourceData = isset($data['source_data_json']) && $data['source_data_json'] !== ''
            ? json_decode($data['source_data_json'], true, 512, JSON_THROW_ON_ERROR)
            : ($existing?->source_data ?? []);
        $pricing = $existing?->pricing ?? [];
        if ($builder) {
            $guestTypes = collect($data['guest_types'] ?? [])->map(fn (array $guestType) => [
                'id' => $guestType['id'],
                'label' => trim($guestType['label']),
                'age' => trim($guestType['age'] ?? ''),
                'role' => $guestType['role'],
            ])->values()->all();
            $guestTypeIds = array_column($guestTypes, 'id');
            $departurePrices = [];
            foreach ($departures as $index => $date) {
                $prices = collect($data['departure_prices'][$index] ?? [])
                    ->filter(fn ($price) => $price !== null && $price !== '')
                    ->map(fn ($price) => (int) $price)
                    ->all();
                if ($prices !== []) {
                    $departurePrices[$date] = $prices;
                }
            }
            $departurePaxTiers = [];
            if (($data['type'] ?? null) === 'Flight + hotel') {
                foreach ($departures as $index => $date) {
                    $tiers = collect($data['departure_pax_tiers'][$index] ?? [])
                        ->map(fn (array $tier) => [
                            'from' => filled($tier['from'] ?? null) ? (int) $tier['from'] : null,
                            'to' => filled($tier['to'] ?? null) ? (int) $tier['to'] : null,
                            'prices' => collect($tier['prices'] ?? [])->only($guestTypeIds)
                                ->filter(fn ($price) => filled($price))
                                ->map(fn ($price) => (int) $price)
                                ->all(),
                        ])->values()->all();
                    if ($tiers !== []) {
                        $departurePaxTiers[$date] = $tiers;
                    }
                }
            }
            $cabinPrices = collect($data['cabin_prices'] ?? [])
                ->mapWithKeys(fn (array $cabin) => [$cabin['type'] => (int) $cabin['price']])
                ->all();
            $addons = collect($data['addons'] ?? [])->map(fn (array $addon) => [
                'id' => $addon['id'],
                'label' => trim($addon['label']),
                'price' => (int) $addon['price'],
            ])->values()->all();
            $pricing = array_merge($pricing, [
                'guest_types' => $guestTypes,
                'departure_prices' => $departurePrices,
                'departure_pax_tiers' => $departurePaxTiers,
                'cabin_prices' => $cabinPrices,
                'addons' => $addons,
            ]);
        }
        $cleanList = static fn (string $key): array => collect($data[$key] ?? [])
            ->filter(fn ($item): bool => is_string($item))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
        $included = $request->boolean('included_present')
            ? $cleanList('included')
            : $existing?->getAttribute('included');
        $excluded = $request->boolean('excluded_present')
            ? $cleanList('excluded')
            : $existing?->getAttribute('excluded');
        $attributes = [
            'name' => $data['name'],
            'slug' => $slug,
            'country' => $data['country'] ?? ($existing?->country ?? 'Unspecified'),
            'country_id' => $data['country_id'] ?? $existing?->country_id,
            'region' => $data['region'] ?? ($existing?->region ?? 'Other'),
            'type' => $data['type'] ?? ($existing?->type ?? 'Adventure'),
            'ship' => $data['ship'] ?? null,
            'brand' => $data['brand'] ?? ($existing?->brand),
            'operator' => $data['operator'] ?? ($existing?->operator),
            'days' => (int) ($data['days'] ?? $existing?->days ?? 1),
            'nights' => (int) ($data['nights'] ?? $existing?->nights ?? max(0, ((int) ($data['days'] ?? 1)) - 1)),
            'description' => $data['description'] ?? '',
            'long_description' => $data['long_description'] ?? ($data['details'] ?? ''),
            'details' => $data['details'] ?? ($data['long_description'] ?? ''),
            'highlights' => $builder ? array_values(array_filter($data['highlights'] ?? [])) : ($existing?->highlights ?? []),
            'included' => $included,
            'excluded' => $excluded,
            'departures' => array_values(array_unique($departures ?: [])),
            'trip_plan' => $tripPlan,
            'price_from' => (int) ($data['price_from'] ?? $existing?->price_from ?? 1),
            'price_to' => (int) ($data['price_to'] ?? $existing?->price_to ?? 1),
            'departure_port' => $data['departure_port'] ?? null,
            'destination_port' => $data['destination_port'] ?? null,
            'destinations' => isset($data['destinations_text']) ? preg_split('/\r\n|\r|\n/', $data['destinations_text'], -1, PREG_SPLIT_NO_EMPTY) : ($existing?->destinations ?? []),
            'image' => $data['image'] ?? ($existing?->image ?? '1624253321171-1be53e12f5f4'),
            'source_data' => isset($data['source_data_json']) && $data['source_data_json'] !== ''
                ? $sourceData
                : ($existing?->source_data ?? []),
            'pricing' => $pricing,
            'itinerary' => array_key_exists('itinerary', $sourceData) ? $sourceData['itinerary'] : ($existing?->itinerary ?? []),
            'published' => $builder ? ! $draft : $request->boolean('published'),
        ];
        if (! $package) {
            $package = new TravelPackage;
        }
        $package->fill($attributes)->save();

        return redirect()->route('admin.packages')->with('success', 'Package saved.');
    }

    public function deletePackage(TravelPackage $package): RedirectResponse
    {
        $package->delete();

        return redirect()->route('admin.packages')->with('success', 'Package deleted.');
    }

    public function resource(Request $request, string $resource): View|RedirectResponse
    {
        if ($resource === 'company') {
            return view('admin.resource', ['title' => 'Company profile', 'resource' => $resource, 'rows' => collect()]);
        }
        if ($resource === 'bookings') {
            $query = Booking::with(['package', 'user', 'cancelledBy'])->latest();
            if ($request->filled('q')) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($builder) => $builder->where('reference', 'like', $term)
                    ->orWhere('order_reference', 'like', $term)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('package', fn ($pkg) => $pkg->where('name', 'like', $term)));
            }
            if ($request->filled('status') && $request->query('status') !== 'All') {
                $query->where('status', $request->query('status'));
            }

            return view('admin.resource', ['title' => 'Bookings', 'resource' => $resource, 'rows' => $query->paginate(20)->withQueryString()]);
        }
        if ($resource === 'deals') {
            $query = TravelDeal::with('package')->orderByDesc('created_at');
            $promoCodes = PromoCode::with('packages')->orderByDesc('created_at');
            if ($request->filled('q')) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('type', 'like', $term)
                    ->orWhereHas('package', fn ($pkg) => $pkg->where('name', 'like', $term)));
                $promoCodes->where(fn ($builder) => $builder->where('code', 'like', $term)
                    ->orWhere('terms', 'like', $term)
                    ->orWhereHas('packages', fn ($package) => $package->where('name', 'like', $term)));
            }

            return view('admin.resource', [
                'title' => 'Deals',
                'resource' => $resource,
                'rows' => $query->get(),
                'promoCodes' => $promoCodes->get(),
            ]);
        }
        if ($resource === 'enquiries') {
            $query = Enquiry::with('latestReply.admin')->latest();
            if ($request->filled('q')) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhere('subject', 'like', $term)->orWhere('message', 'like', $term));
            }
            if ($request->query('status') === 'Unanswered') {
                $query->whereIn('status', ['New', 'In progress']);
            } elseif ($request->query('status') === 'Replied') {
                $query->whereIn('status', ['Replied', 'Resolved']);
            }

            return view('admin.resource', [
                'title' => 'Enquiries',
                'resource' => $resource,
                'rows' => $query->paginate(20)->withQueryString(),
                'waitingCount' => Enquiry::whereIn('status', ['New', 'In progress'])->count(),
            ]);
        }
        if ($resource === 'guides') {
            $query = Guide::orderBy('sort_order')->orderBy('title');
            if ($request->filled('q')) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($builder) => $builder->where('title', 'like', $term)->orWhere('category', 'like', $term));
            }

            return view('admin.resource', ['title' => 'Guides', 'resource' => $resource, 'rows' => $query->get()]);
        }
        if ($resource === 'media') {
            $files = AssetLibrary::all();

            return view('admin.resource', ['title' => 'Media', 'resource' => $resource, 'rows' => $files, 'mediaBytes' => $files->sum('size')]);
        }
        if ($resource === 'users') {
            $users = User::query()->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })->orderByDesc('created_at')->limit(100)->get();
            $selectedUser = User::query()->find($request->integer('user'));
            $lastActive = $selectedUser
                ? DB::table('sessions')->where('user_id', $selectedUser->id)->max('last_activity')
                : null;

            return view('admin.resource', [
                'title' => 'Users', 'resource' => $resource, 'rows' => $users,
                'selectedUser' => $selectedUser,
                'selectedLastActive' => $lastActive ? Carbon::createFromTimestamp((int) $lastActive) : null,
                'selectedBookings' => $selectedUser?->bookings()->count() ?? 0,
                'selectedSpend' => $selectedUser?->bookings()->where('status', 'Confirmed')->sum('total') ?? 0,
            ]);
        }

        abort(404);
    }

    public function showBooking(Booking $booking): View
    {
        return view('admin.bookings.show', ['booking' => $booking->load(['package', 'user', 'cancelledBy'])]);
    }

    public function createDeal(): View
    {
        return view('admin.deals.form', [
            'deal' => new TravelDeal(['active' => true]),
            'packages' => TravelPackage::orderBy('name')->get(),
        ]);
    }

    public function editDeal(TravelDeal $deal): View
    {
        return view('admin.deals.form', [
            'deal' => $deal,
            'packages' => TravelPackage::orderBy('name')->get(),
        ]);
    }

    public function storeDeal(SaveAdminDealRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $baseSlug = Str::slug($data['name']) ?: 'deal';
        $slug = $baseSlug;
        $suffix = 2;
        while (TravelDeal::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }
        do {
            $promoCode = Str::upper(Str::random(10));
        } while (TravelDeal::where('promo_code', $promoCode)->exists());

        TravelDeal::create([
            ...$data,
            'slug' => $slug,
            'promo_code' => $promoCode,
            'description' => $data['terms'],
            'ends_at' => filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at'])->toDateString() : null,
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('admin.deals')->with('success', 'Deal saved');
    }

    public function updateDeal(SaveAdminDealRequest $request, TravelDeal $deal): RedirectResponse
    {
        $data = $request->validated();
        if ($deal->expires_at?->isPast()
            && filled($data['expires_at'] ?? null)
            && Carbon::parse($data['expires_at'])->format('Y-m-d H:i') === $deal->expires_at->format('Y-m-d H:i')) {
            $data['expires_at'] = $deal->expires_at;
        }
        $deal->update([
            ...$data,
            'description' => $data['terms'],
            'ends_at' => filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at'])->toDateString() : null,
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('admin.deals')->with('success', 'Deal saved');
    }

    public function toggleDeal(TravelDeal $deal): RedirectResponse
    {
        $deal->update(['active' => ! $deal->active]);

        return back()->with('success', $deal->active ? 'Deal resumed.' : 'Deal paused.');
    }

    public function deleteDeal(TravelDeal $deal): RedirectResponse
    {
        $deal->delete();

        return redirect()->route('admin.deals')->with('success', 'Deal deleted.');
    }

    public function createPromoCode(): View
    {
        return view('admin.promos.form', [
            'promoCode' => new PromoCode(['all_packages' => true, 'active' => true, 'discount_type' => 'percent']),
            'packages' => TravelPackage::query()->where('published', true)->orderBy('name')->get(),
        ]);
    }

    public function editPromoCode(PromoCode $promoCode): View
    {
        return view('admin.promos.form', [
            'promoCode' => $promoCode->load('packages'),
            'packages' => TravelPackage::query()->where('published', true)->orderBy('name')->get(),
        ]);
    }

    public function storePromoCode(SaveAdminPromoCodeRequest $request): RedirectResponse
    {
        $this->persistPromoCode($request);

        return redirect()->route('admin.deals')->with('success', 'Promo code saved.');
    }

    public function updatePromoCode(SaveAdminPromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $this->persistPromoCode($request, $promoCode);

        return redirect()->route('admin.deals')->with('success', 'Promo code saved.');
    }

    public function togglePromoCode(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update(['active' => ! $promoCode->active]);

        return back()->with('success', $promoCode->active ? 'Promo code resumed.' : 'Promo code paused.');
    }

    public function deletePromoCode(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->delete();

        return redirect()->route('admin.deals')->with('success', 'Promo code deleted.');
    }

    private function persistPromoCode(SaveAdminPromoCodeRequest $request, ?PromoCode $promoCode = null): PromoCode
    {
        $data = $request->validated();
        $allPackages = (bool) $data['all_packages'];

        return DB::transaction(function () use ($data, $request, $promoCode, $allPackages): PromoCode {
            $promoCode ??= new PromoCode;
            $promoCode->fill([
                'code' => mb_strtoupper(trim($data['code'])),
                'discount_type' => $data['discount_type'],
                'discount_percent' => $data['discount_type'] === 'percent' ? (float) $data['discount_value'] : 0,
                'discount_amount' => $data['discount_type'] === 'fixed' ? (float) $data['discount_value'] : null,
                'all_packages' => $allPackages,
                'terms' => $data['terms'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'active' => $request->boolean('active'),
            ]);
            $promoCode->save();
            $promoCode->packages()->sync($allPackages ? [] : $data['package_ids']);

            return $promoCode;
        });
    }

    public function showEnquiry(Enquiry $enquiry): View
    {
        return view('admin.enquiries.show', [
            'enquiry' => $enquiry->load(['replies.admin']),
            'latestReply' => $enquiry->latestReply()->with('admin')->first(),
        ]);
    }

    public function replyToEnquiry(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $request->merge([
            'subject' => preg_replace('/[\r\n]+/', ' ', (string) $request->input('subject', '')),
        ]);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        $admin = $request->user();
        $reply = new EnquiryReply([
            'enquiry_id' => $enquiry->id,
            'admin_id' => $admin->id,
            'subject' => $data['subject'],
            'body' => $data['message'],
            'status' => 'failed',
        ]);
        $company = config('company');

        try {
            Mail::to($enquiry->email)->send(new EnquiryReplyMail(
                $data['subject'],
                $data['message'],
                $enquiry->message,
                $company,
            ));
        } catch (\Throwable $exception) {
            $reply->error = 'The email could not be sent.';
            $reply->save();
            Log::error('Admin enquiry email failed.', [
                'exception' => $exception::class,
                'enquiry_reply_id' => $reply->id,
            ]);

            return redirect()->route('admin.enquiries.show', $enquiry)
                ->with('error', 'The email could not be sent. Check the mail settings and try again.')
                ->withInput($request->only('subject', 'message'));
        }

        $reply->status = 'sent';
        $reply->sent_at = now();
        $reply->save();
        $enquiry->update(['status' => 'Replied']);

        return redirect()->route('admin.enquiries.show', $enquiry)
            ->with('success', 'Reply sent to '.$enquiry->email);
    }

    public function storeGuide(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'alpha_dash', 'max:200', 'unique:guides,slug'],
            'category' => ['required', 'string', 'max:100'],
            'read_time' => ['nullable', 'string', 'max:40'],
            'image' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:100000'],
            'published' => ['nullable', 'boolean'],
        ]);
        $data['slug'] = $this->uniqueGuideSlug($data['slug'] ?: Str::slug($data['title']));
        $data['published'] = $request->boolean('published');
        $data['sort_order'] = Guide::max('sort_order') + 1;
        Guide::create($data);

        return redirect()->route('admin.guides')->with('success', 'Guide saved.');
    }

    public function createGuide(): View
    {
        return view('admin.guides.form', [
            'guide' => new Guide,
            'mediaAssets' => AssetLibrary::all(),
            'mediaFolders' => AssetLibrary::folders(),
        ]);
    }

    public function editGuide(Guide $guide): View
    {
        return view('admin.guides.form', [
            'guide' => $guide,
            'mediaAssets' => AssetLibrary::all(),
            'mediaFolders' => AssetLibrary::folders(),
        ]);
    }

    public function updateGuide(Request $request, Guide $guide): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'alpha_dash', 'max:200', Rule::unique('guides', 'slug')->ignore($guide->id)],
            'category' => ['required', 'string', 'max:100'],
            'read_time' => ['nullable', 'string', 'max:40'],
            'image' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:100000'],
            'published' => ['nullable', 'boolean'],
        ]);
        $data['published'] = $request->boolean('published');
        $guide->update($data);

        return redirect()->route('admin.guides')->with('success', 'Guide saved.');
    }

    public function uploadMedia(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000', 'mimes:jpg,jpeg,png,webp,gif,avif,mp4,webm,mov,m4v'],
        ]);

        $asset = AssetLibrary::store($request->file('file'));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media uploaded.', 'asset' => $asset]);
        }

        return redirect()->route('admin.media')->with('success', $asset['name'].' uploaded.');
    }

    public function deleteMedia(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:1024'],
        ]);
        $deleted = AssetLibrary::delete($data['path']);

        if ($deleted === null) {
            abort(404);
        }

        if (! $deleted) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to delete this media file.'], 500);
            }

            return redirect()->route('admin.media')->with('error', 'Unable to delete '.$data['path'].'.');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media deleted.']);
        }

        return redirect()->route('admin.media')->with('success', basename($data['path']).' deleted.');
    }

    public function renameMedia(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:1024'],
            'name' => ['required', 'string', 'max:180'],
        ]);
        $asset = AssetLibrary::rename($data['path'], $data['name']);

        if ($asset === null) {
            abort(404);
        }

        if ($asset === false) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to rename this media file.'], 500);
            }

            return redirect()->route('admin.media')->with('error', 'Unable to rename '.basename($data['path']).'.');
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media renamed.', 'asset' => $asset]);
        }

        return redirect()->route('admin.media')->with('success', basename($data['path']).' renamed to '.$asset['name'].'.');
    }

    public function updateStatus(Request $request, string $resource, int $id): RedirectResponse
    {
        if ($resource === 'bookings') {
            $data = $request->validate(['status' => ['required', Rule::in(['Confirmed', 'Pending payment', 'Cancelled'])]]);
            Booking::findOrFail($id)->update($data);
        } elseif ($resource === 'enquiries') {
            $data = $request->validate(['status' => ['required', Rule::in(['New', 'In progress', 'Resolved', 'Replied'])]]);
            Enquiry::findOrFail($id)->update($data);
        } else {
            abort(404);
        }

        return back()->with('success', 'Status updated.');
    }

    private function packageStatus(TravelPackage $package): string
    {
        if (! $package->published) {
            return 'Draft';
        }
        $dates = collect($package->departures ?? [])->map(fn ($date) => Carbon::parse($date))->filter(fn (Carbon $date) => $date->isFuture());
        if ($dates->isNotEmpty() && $dates->min()->lte(now()->addDays(7))) {
            return 'Expiring';
        }

        return 'Published';
    }

    private function uniqueGuideSlug(string $slug): string
    {
        $base = $slug;
        $suffix = 2;
        while (Guide::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function expiryCounts(Collection $packages): array
    {
        $packageDepartures = $packages->map(fn (TravelPackage $package) => collect($package->departures ?? [])
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())->sort()->values());
        $nextDepartures = $packageDepartures->map(fn (Collection $dates) => $dates->first(fn (Carbon $date) => $date->isFuture()))->filter();
        $lastDepartures = $packageDepartures->map(fn (Collection $dates) => $dates->last())->filter();

        return [
            'expired' => $lastDepartures->filter(fn (Carbon $date) => $date->between(now()->subDays(7)->startOfDay(), today()))->count(),
            'expiring' => $nextDepartures->filter(fn (Carbon $date) => $date->lte(now()->addDays(7)->endOfDay()))->count(),
            'next' => $nextDepartures->sort()->first(),
        ];
    }
}
