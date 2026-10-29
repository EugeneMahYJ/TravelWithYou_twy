<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\Guide;
use App\Models\NewsletterSubscriber;
use App\Models\PromoCode;
use App\Models\SearchLog;
use App\Models\Ship;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use App\Support\Pricing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TravelController extends Controller
{
    public function home(): Response
    {
        $destinations = Country::query()->inRandomOrder()->limit(4)->get()->shuffle()->values();

        return response()->view('home', [
            'featured' => TravelPackage::where('published', true)->orderByDesc('reviews')->take(6)->get(),
            'destinations' => $destinations,
            'ships' => Ship::query()->orderBy('id')->get(),
            'journalPosts' => Guide::where('published', true)->orderBy('sort_order')->take(3)->get(),
            'searchOptions' => $this->searchOptions(),
            'slides' => [
                ['image' => 'home/danube.jpg', 'kicker' => 'Limited-time · 25% off', 'title' => 'Welcome to Travel With You! ', 'text' => 'Celebrate Lunar New Year along Europe\'s legendary Danube.', 'label' => 'Book Now', 'url' => route('packages.show', ['package' => 'amaasia-lunar-new-year-best-of-the-danube-2027'])],
                ['image' => 'star-voyager-1-xnakcrup.png', 'kicker' => 'New ship · Star Voyager', 'title' => 'Sail the Star Voyager.', 'text' => 'A boutique ship for hidden harbours, from Melaka to Singapore.', 'label' => 'View ship', 'url' => route('ships.show', ['ship' => 'star-voyager']), 'secondary_label' => 'Explore Cruise', 'secondary_url' => route('ships')],
                ['image' => 'ship-navigator-of-the-seas-01', 'video' => asset('assets/video/Ship_example.mp4'), 'kicker' => 'Ship tour · Navigator of the Seas', 'title' => 'Amped up at sea.', 'text' => 'Step aboard Navigator of the Seas — record-breaking slides, a bigger pool deck and the Royal Promenade.', 'label' => 'View ship', 'url' => route('ships.show', ['ship' => 'navigator-of-the-seas']), 'secondary_label' => 'Explore Cruise', 'secondary_url' => route('ships')],
                ['image' => 'package-photo-20', 'kicker' => 'Early bird · 15% off', 'title' => 'Above the cloud line.', 'text' => 'Machu Picchu at sunrise with an archaeologist at your side.', 'label' => 'View Deal', 'url' => route('deals')],
                ['image' => 'package-photo-24', 'kicker' => 'Group discount · 10% off', 'title' => 'The Great Migration.', 'text' => 'Tented camps on the Mara. Bring five friends and save.', 'label' => 'View Deal', 'url' => route('deals')],
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function packages(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'ship' => ['nullable', 'string', 'max:120'],
            'dep_port' => ['nullable', 'string', 'max:120'],
            'dest_port' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'in:lt2,2-3.5,3.5-5,gt5'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:120'],
            'duration' => ['nullable', 'in:1-7,8-10,11-14,15'],
            'sort' => ['nullable', 'in:price-asc,price-desc,rating,duration'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = TravelPackage::query()->where('published', true);
        $query->when($request->filled('q'), function ($builder) use ($request) {
            $term = '%'.Str::lower(trim($request->string('q')->toString())).'%';
            $builder->where(function ($matches) use ($term) {
                foreach (['name', 'description', 'country', 'type'] as $column) {
                    $matches->orWhereRaw('LOWER('.$column.') LIKE ?', [$term]);
                }
            });
        });
        foreach (['ship' => 'ship', 'country' => 'country', 'region' => 'region', 'type' => 'type', 'dep_port' => 'departure_port', 'dest_port' => 'destination_port'] as $filter => $column) {
            if ($request->filled($filter)) {
                $query->whereRaw('LOWER('.$column.') = ?', [Str::lower($request->string($filter)->toString())]);
            }
        }
        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            $matchingIds = TravelPackage::query()->where('published', true)->get(['id', 'departures'])
                ->filter(fn (TravelPackage $package) => collect($package->departures)->contains(fn ($date) => str_starts_with($date, $month)))
                ->modelKeys();
            $query->whereIn('id', $matchingIds);
        }
        if ($request->filled('price')) {
            match ($request->string('price')->toString()) {
                'lt2' => $query->where('price_from', '<', 2000),
                '2-3.5' => $query->whereBetween('price_from', [2000, 3500]),
                '3.5-5' => $query->whereBetween('price_from', [3500, 5000]),
                'gt5' => $query->where('price_from', '>=', 5000),
            };
        }
        if ($request->filled('duration')) {
            match ($request->string('duration')->toString()) {
                '1-7' => $query->whereBetween('days', [1, 7]),
                '8-10' => $query->whereBetween('days', [8, 10]),
                '11-14' => $query->whereBetween('days', [11, 14]),
                '15' => $query->where('days', '>=', 15),
            };
        }
        match ($request->input('sort', 'price-asc')) {
            'price-desc' => $query->orderByDesc('price_from'),
            'rating' => $query->orderByDesc('rating'),
            'duration' => $query->orderBy('days'),
            default => $query->orderBy('price_from'),
        };

        $packages = $query->paginate(12)->withQueryString();
        $searchTerm = trim($request->string('q')->toString());
        $activeFilters = collect($filters)->except(['page', 'sort'])->filter(fn ($value) => $value !== null && $value !== '')->all();
        if ($searchTerm !== '' || $activeFilters !== []) {
            SearchLog::create([
                'user_id' => Auth::id(),
                'query' => $searchTerm !== '' ? $searchTerm : null,
                'filters' => $activeFilters,
                'results_count' => $packages->total(),
            ]);
        }

        return view('packages.index', [
            'packages' => $packages,
            'regions' => Country::query()->select('region')->distinct()->orderBy('region')->pluck('region'),
            'countries' => Country::query()->orderBy('name')->pluck('name'),
            'types' => TravelPackage::query()->where('published', true)->distinct()->orderBy('type')->pluck('type'),
            'searchOptions' => $this->searchOptions(),
            'activeFilters' => $filters,
        ]);
    }

    public function package(TravelPackage $package): View
    {
        abort_unless($package->published, 404);

        $ship = $package->ship
            ? Ship::query()->with('cabins')->where('name', $package->ship)->first()
            : null;
        $gallery = collect($package->gallery ?? [])->filter()->unique()->values();
        if ($gallery->isEmpty()) {
            $gallery->push($package->image);
        }

        return view('packages.show', [
            'package' => $package,
            'deal' => Pricing::dealFor($package),
            'ship' => $ship,
            'gallery' => $gallery->map(fn ($image, $index) => [
                'image' => $image,
                'caption' => ($index === 0 ? 'Destination highlight' : ($index === 1 ? 'Scenery along the way' : 'Trip atmosphere')).': '.$package->name,
            ]),
            'countryRecord' => $package->countryRecord ?? Country::query()->where('name', $package->country)->first(),
            'highlights' => $package->highlights ?: ($package->source_data['highlights'] ?? $package->source_data['attractions'] ?? $package->source_data['highlight_badges'] ?? []),
            'related' => TravelPackage::query()
                ->where('published', true)
                ->where('id', '!=', $package->id)
                ->where(fn ($query) => $query->where('country', $package->country)->orWhere('type', $package->type))
                ->orderByRaw('CASE WHEN country = ? THEN 0 ELSE 1 END', [$package->country])
                ->take(3)
                ->get(),
        ]);
    }

    public function packagePdf(Request $request, TravelPackage $package): Response
    {
        abort_unless($package->published, 404);
        $brochure = config('package_brochures.by_source_id.'.$package->source_id);
        if ($brochure !== null) {
            $path = public_path('assets/Package_Example/'.$brochure);
            abort_unless(is_file($path), 404);

            $filename = $package->slug.'-TravelWithYou.pdf';

            return $request->boolean('download')
                ? response()->download($path, $filename, ['Content-Type' => 'application/pdf'])
                : response()->file($path, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.$filename.'"',
                ]);
        }

        $ship = $package->ship
            ? Ship::query()->with('cabins')->where('name', $package->ship)->first()
            : null;
        $filename = $package->slug.'-TravelWithYou.pdf';
        $pdf = Pdf::loadView('packages.pdf', [
            'package' => $package,
            'ship' => $ship,
        ])->setPaper('a4');

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function bookingForm(Request $request, string $id, string $date): View
    {
        $package = TravelPackage::query()->where('slug', $id)->where('published', true)->first();
        if (! $package) {
            return view('booking', ['state' => 'package-not-found']);
        }
        $editCartItem = $this->requestedCartItem($request, $package);
        if (! in_array($date, Pricing::availableDepartureDates($package), true)) {
            return view('booking', ['state' => 'departure-unavailable', 'package' => $package]);
        }

        $ship = $package->ship
            ? Ship::query()->with('cabins')->where('name', $package->ship)->first()
            : null;
        $cruise = $package->type === 'Cruise' && $ship !== null;
        if ($cruise && $ship->cabins->isEmpty()) {
            return view('booking', ['state' => 'no-cabins', 'package' => $package]);
        }

        $gallery = collect($ship?->gallery ?? [])->filter()->values();
        if ($gallery->isEmpty() && $ship?->image) {
            $gallery->push($ship->image);
        }
        $deal = Pricing::dealFor($package);
        $cabinPrices = Pricing::cabinPrices($package);
        $errorBag = $request->session()->get('errors');
        $oldInput = old();
        if ($editCartItem !== null && $oldInput === []) {
            $oldInput = $this->bookingInputFromCartItem($editCartItem['item'], $package);
        }
        $submitParameters = ['id' => $package->slug, 'date' => $date];
        if ($editCartItem !== null) {
            $submitParameters['cart_item'] = $request->query('cart_item');
        }

        return view('booking', [
            'state' => null,
            'package' => $package,
            'date' => $date,
            'ship' => $ship,
            'cruise' => $cruise,
            'deal' => $deal ? [
                'type' => $deal->type,
                'percent' => (float) $deal->discount_percent,
            ] : null,
            'taxRate' => (float) config('travel.tax_rate'),
            'guestKinds' => Pricing::guestKinds($package),
            'addons' => Pricing::addons($package),
            'cabins' => $ship?->cabins->values()->map(fn ($cabin, $index) => [
                'type' => $cabin->type,
                'size' => $cabin->size,
                'price' => (float) ($cabinPrices[$cabin->type] ?? $cabin->price_from),
                'note' => $cabin->note,
                'image' => asset_img($gallery->isEmpty() ? $ship->image : $gallery[$index % $gallery->count()]),
            ])->all() ?? [],
            'packageImage' => asset_img($package->image),
            'packageUrl' => route('packages.show', ['package' => $package->slug]),
            'submitUrl' => route('booking.submit', $submitParameters),
            'editing' => $editCartItem !== null,
            'oldInput' => $oldInput,
            'errors' => $errorBag?->getBag('default')->messages() ?? [],
        ]);
    }

    public function submitBooking(Request $request, string $id, string $date): RedirectResponse
    {
        $package = TravelPackage::query()->where('slug', $id)->where('published', true)->firstOrFail();
        $editCartItem = $this->requestedCartItem($request, $package);
        $bookingParameters = ['id' => $id, 'date' => $date];
        if ($editCartItem !== null) {
            $bookingParameters['cart_item'] = $request->query('cart_item');
        }
        $bookingUrl = route('booking', $bookingParameters);
        if (! in_array($date, Pricing::availableDepartureDates($package), true)) {
            return redirect($bookingUrl)->withErrors(['departure' => "That departure isn't available."]);
        }

        $ship = $package->ship
            ? Ship::query()->with('cabins')->where('name', $package->ship)->first()
            : null;
        $cruise = $package->type === 'Cruise' && $ship !== null;
        if ($cruise && $ship->cabins->isEmpty()) {
            return redirect($bookingUrl);
        }

        $kind = $cruise ? 'cruise' : 'fh';
        $rules = [
            'action' => ['required', Rule::in(['proceed', 'cart'])],
            'kind' => ['required', Rule::in([$kind])],
            'addons' => ['sometimes', 'array'],
            'addons.*' => ['string', 'distinct', Rule::in(collect(Pricing::addons($package))->pluck('id')->all())],
        ];

        $guestKinds = Pricing::guestKinds($package);
        $rules['counts'] = [$cruise ? 'sometimes' : 'required', 'array'];
        foreach ($guestKinds as $guestKind) {
            $rules['counts.'.$guestKind['id']] = [$cruise ? 'sometimes' : 'required', 'integer', 'min:0', 'max:16'];
        }
        if ($cruise) {
            $rules += [
                'adults' => ['nullable', 'integer', 'min:0', 'max:16'],
                'children' => ['nullable', 'integer', 'min:0', 'max:16'],
                'rooms' => ['required', 'integer', 'min:1', 'max:8'],
                'room_types' => ['required', 'array'],
                'room_types.*' => ['required', 'string', 'max:120'],
            ];
        }
        $rules += [
            'guests' => ['sometimes', 'array'],
            'guests.*' => ['required', 'array'],
            'guests.*.name' => ['nullable', 'string', 'max:120'],
            'guests.*.dob' => ['nullable', 'string', 'max:10'],
            'guests.*.nation' => ['nullable', 'string', 'max:120'],
            'guests.*.passport' => ['nullable', 'string', 'max:12'],
            'guests.*.passportExpiry' => ['nullable', 'string', 'max:10'],
            'guests.*.email' => ['nullable', 'string', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:40'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'guests.*.passport.max' => '6 to 12 letters or digits',
            'guests.array' => 'Please complete the highlighted guest details.',
            'guests.*.required' => 'Please complete the highlighted guest details.',
            'guests.*.array' => 'Please complete the highlighted guest details.',
            'room_types.required' => 'Choose a room type for every room.',
            'room_types.array' => 'Choose a room type for every room.',
        ]);
        if ($validator->fails()) {
            return redirect($bookingUrl)
                ->withErrors($validator)
                ->withInput($this->safeBookingInput($request));
        }
        $input = $validator->validated();
        $addons = array_values($input['addons'] ?? []);

        if ($cruise) {
            $counts = collect($guestKinds)->mapWithKeys(fn ($guestKind) => [
                $guestKind['id'] => (int) ($input['counts'][$guestKind['id']] ?? 0),
            ])->all();
            if (! isset($input['counts'])) {
                $legacyCounts = ['adult' => (int) ($input['adults'] ?? 0), 'childTwin' => (int) ($input['children'] ?? 0)];
                foreach ($legacyCounts as $legacyId => $count) {
                    if (array_key_exists($legacyId, $counts)) {
                        $counts[$legacyId] = $count;
                    }
                }
            }
            $adults = collect($guestKinds)->filter(fn ($guestKind) => ($guestKind['role'] ?? 'adult') === 'adult')
                ->sum(fn ($guestKind) => $counts[$guestKind['id']]);
            $singles = (int) ($counts['single'] ?? 0);
            $sharingAdults = $adults - $singles;
            $children = collect($guestKinds)->filter(fn ($guestKind) => ($guestKind['role'] ?? 'adult') === 'child')
                ->sum(fn ($guestKind) => $counts[$guestKind['id']]);
            $total = $adults + $children;
            $rooms = (int) $input['rooms'];
            $roomTypes = $input['room_types'];
            if ($adults < 1) {
                $errors = ['adults' => 'At least 1 adult must travel on each booking.'];
            } elseif ($total > 16) {
                $errors = ['adults' => 'Online bookings are limited to 16 guests. Contact us for larger groups.'];
            } elseif ($roomError = Pricing::roomError($sharingAdults, $children, $rooms, $singles)) {
                $errors = ['rooms' => $roomError];
            } elseif (count($roomTypes) !== $rooms || collect($roomTypes)->contains(fn ($type) => ! $ship->cabins->contains('type', $type))) {
                $errors = ['room_types' => 'Choose a room type for every room.'];
            } else {
                $errors = [];
            }
            $roomPlan = collect(Pricing::allocateRooms($sharingAdults, $children, $rooms, $singles))
                ->values()
                ->map(fn ($room, $index) => [...$room, 'type' => $roomTypes[$index] ?? ''])
                ->all();
        } else {
            $counts = collect($guestKinds)
                ->mapWithKeys(fn ($guestKind) => [$guestKind['id'] => (int) $input['counts'][$guestKind['id']]])
                ->all();
            $total = array_sum($counts);
            $grownups = collect($guestKinds)->filter(fn ($guestKind) => ($guestKind['role'] ?? 'adult') === 'adult')
                ->sum(fn ($guestKind) => $counts[$guestKind['id']]);
            if ($total === 0) {
                $errors = ['counts' => 'Add at least one guest to continue.'];
            } elseif ($grownups < 1) {
                $errors = ['counts' => 'Children must travel with at least one Adult or Single guest.'];
            } elseif ($total > 12) {
                $errors = ['counts' => 'Online bookings are limited to 12 guests. Contact us for larger groups.'];
            } else {
                $errors = [];
            }
            $roomPlan = [];
        }

        $guestInputs = $input['guests'] ?? [];
        if (count($guestInputs) !== $total) {
            $errors['guests'] = 'Please complete the highlighted guest details.';
        }
        $normalizedGuests = [];
        $minimumExpiry = Carbon::parse($date)->addMonths(6)->toDateString();
        foreach (array_values($guestInputs) as $index => $guest) {
            $lead = $index === 0;
            $name = trim((string) ($guest['name'] ?? ''));
            $dob = (string) ($guest['dob'] ?? '');
            $nation = trim((string) ($guest['nation'] ?? ''));
            $passport = Str::upper(trim((string) ($guest['passport'] ?? '')));
            $passportExpiry = (string) ($guest['passportExpiry'] ?? '');
            $email = trim((string) ($guest['email'] ?? ''));
            $phone = trim((string) ($guest['phone'] ?? ''));
            $prefix = 'guests.'.$index.'.';

            if (mb_strlen($name) < 3 || preg_match('/[A-Za-z]{2}/', $name) !== 1) {
                $errors[$prefix.'name'] = 'Enter the full name as on the passport';
            }
            $validDob = $this->validBookingDate($dob)
                && (int) substr($dob, 0, 4) >= 1900
                && ! Carbon::parse($dob)->isFuture();
            if (! $validDob) {
                $errors[$prefix.'dob'] = 'Enter a valid date of birth';
            } else {
                $guestKindOffset = 0;
                $guestKind = null;
                foreach ($guestKinds as $kindDefinition) {
                    $kindCount = (int) ($counts[$kindDefinition['id']] ?? 0);
                    if ($index < $guestKindOffset + $kindCount) {
                        $guestKind = $kindDefinition;
                        break;
                    }
                    $guestKindOffset += $kindCount;
                }

                if ($guestKind !== null) {
                    $birthDate = Carbon::parse($dob);
                    $bookingDate = Carbon::parse($date);
                    $age = (int) $bookingDate->format('Y') - (int) $birthDate->format('Y');
                    if ($bookingDate->format('md') < $birthDate->format('md')) {
                        $age--;
                    }
                    if (($guestKind['id'] ?? null) === 'single' && $age < 18) {
                        $errors[$prefix.'dob'] = 'Single guests must be at least 18 years old.';
                    } elseif (($guestKind['role'] ?? 'adult') === 'adult' && $age < 12) {
                        $errors[$prefix.'dob'] = 'Adult guests must be at least 12 years old.';
                    } elseif (($guestKind['role'] ?? 'adult') === 'child' && $age > 11) {
                        $errors[$prefix.'dob'] = 'Children must be 11 years old or younger.';
                    }
                }
            }
            if ($nation === '') {
                $errors[$prefix.'nation'] = 'Required';
            }
            if (preg_match('/\A[A-Z0-9]{6,12}\z/', $passport) !== 1) {
                $errors[$prefix.'passport'] = '6 to 12 letters or digits';
            }
            if (! $this->validBookingDate($passportExpiry)) {
                $errors[$prefix.'passportExpiry'] = 'Passport expiry date is required';
            } elseif ($passportExpiry < $minimumExpiry) {
                $errors[$prefix.'passportExpiry'] = 'Passport must be valid for 6 months after departure (on or after '.fmt_date($minimumExpiry).')';
            }
            if (($lead || $email !== '') && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$prefix.'email'] = 'Enter a valid email address';
            }
            if (($lead || $phone !== '') && strlen((string) preg_replace('/\D/', '', $phone)) < 7) {
                $errors[$prefix.'phone'] = 'Enter a valid phone number';
            }
            $normalizedGuests[] = [
                'name' => $name,
                'dob' => $dob,
                'nation' => $nation,
                'passport' => $passport,
                'passportExpiry' => $passportExpiry,
                'email' => $email,
                'phone' => $phone,
            ];
        }

        if ($errors !== []) {
            return redirect($bookingUrl)
                ->withErrors($errors)
                ->withInput($this->safeBookingInput($request));
        }

        $detail = [
            'kind' => $kind,
            'rooms' => $roomPlan,
            'counts' => $counts,
            'guests' => array_map(function (array $guest) {
                $guest['passport'] = Crypt::encryptString($guest['passport']);

                return $guest;
            }, $normalizedGuests),
        ];
        $lines = Pricing::bookingLines($package, $date, $detail, $addons);
        if ($lines === []) {
            return redirect($bookingUrl)->withErrors(['guests' => 'Add at least one guest to continue.']);
        }

        $cart = $request->session()->get('cart', []);
        $cartItem = [
            'package_id' => $package->id,
            'departure_date' => $date,
            'guests' => $total,
            'addons' => $addons,
            'detail' => $detail,
        ];
        if ($editCartItem !== null) {
            $cart[$editCartItem['index']] = [...$editCartItem['item'], ...$cartItem];
        } else {
            $cart[] = ['uid' => (string) Str::uuid(), ...$cartItem];
        }
        $request->session()->put('cart', $cart);

        if ($input['action'] === 'proceed') {
            return redirect()->route('checkout');
        }

        return $editCartItem !== null
            ? redirect()->route('cart')->with('success', 'Booking updated in your cart.')
            : redirect()->route('home')->with('success', 'Added to cart');
    }

    private function safeBookingInput(Request $request): array
    {
        $input = $request->except(['_token', '_method']);
        if (isset($input['guests']) && is_array($input['guests'])) {
            foreach ($input['guests'] as &$guest) {
                if (is_array($guest)) {
                    unset($guest['passport']);
                }
            }
            unset($guest);
        }

        return $input;
    }

    private function requestedCartItem(Request $request, TravelPackage $package): ?array
    {
        $uid = $request->query('cart_item');
        if ($uid === null) {
            return null;
        }

        abort_unless(is_string($uid) && $uid !== '', 404);

        $cart = $request->session()->get('cart', []);
        $index = collect($cart)->search(fn (array $item) => ($item['uid'] ?? null) === $uid);
        abort_unless($index !== false && isset($cart[$index]), 404);
        abort_unless((int) ($cart[$index]['package_id'] ?? 0) === $package->id, 404);
        abort_if(empty($cart[$index]['detail']), 404);

        return ['index' => $index, 'item' => $cart[$index]];
    }

    private function bookingInputFromCartItem(array $item, TravelPackage $package): array
    {
        $detail = $item['detail'];
        $counts = $detail['counts'] ?? [];
        $guestKinds = Pricing::guestKinds($package);
        $roleCounts = collect($guestKinds)->groupBy(fn ($kind) => $kind['role'] ?? 'adult')
            ->map(fn ($kinds) => $kinds->sum(fn ($kind) => (int) ($counts[$kind['id']] ?? 0)));
        $guests = array_map(function (array $guest): array {
            if (! empty($guest['passport'])) {
                $guest['passport'] = Crypt::decryptString($guest['passport']);
            }

            return $guest;
        }, $detail['guests'] ?? []);

        return [
            'counts' => $counts,
            'adults' => $roleCounts->get('adult', 0),
            'children' => $roleCounts->get('child', 0),
            'rooms' => count($detail['rooms'] ?? []),
            'room_types' => array_column($detail['rooms'] ?? [], 'type'),
            'guests' => $guests,
            'addons' => $item['addons'] ?? [],
        ];
    }

    private function validBookingDate(string $date): bool
    {
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    public function destinations(Request $request, ?string $region = null): View
    {
        $selectedRegion = $region ?? $request->query('region');
        $countries = Country::query()
            ->with(['packages' => fn ($query) => $query->where('published', true)->orderBy('price_from')])
            ->when($selectedRegion, fn ($query) => $query->where('region', $selectedRegion))
            ->orderBy('region')
            ->orderBy('name')
            ->get();
        $allCountriesCount = Country::count();
        $regionCount = Country::query()->distinct('region')->count('region');

        return view('destinations.index', [
            'destinations' => $countries,
            'regions' => Country::query()->select('region')->distinct()->orderBy('region')->pluck('region'),
            'selectedRegion' => $selectedRegion,
            'destinationTitle' => Str::ucfirst($this->numberInWords($allCountriesCount)).' countries, '.$this->numberInWords($regionCount).' regions.',
        ]);
    }

    public function country(Request $request, string $country): View
    {
        $destination = Country::where('slug', $country)->firstOrFail();
        $filters = $request->validate([
            'sort' => ['nullable', 'in:rating,price-asc,price-desc,duration'],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'duration' => ['nullable', 'in:short,long'],
            'max_price' => ['nullable', 'in:2500,3500,5000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = TravelPackage::query()->where('published', true)->where('country_id', $destination->id);
        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            $matchingIds = TravelPackage::query()->where('published', true)->where('country_id', $destination->id)
                ->get(['id', 'departures'])
                ->filter(fn (TravelPackage $package) => collect($package->departures)->contains(fn ($date) => str_starts_with($date, $month)))
                ->modelKeys();
            $query->whereIn('id', $matchingIds);
        }
        if ($request->filled('duration')) {
            $query->where('days', $request->input('duration') === 'short' ? '<=' : '>=', $request->input('duration') === 'short' ? 9 : 10);
        }
        if ($request->filled('max_price')) {
            $query->where('price_from', '<=', (int) $request->input('max_price'));
        }
        match ($request->input('sort', 'rating')) {
            'price-asc' => $query->orderBy('price_from'),
            'price-desc' => $query->orderByDesc('price_from'),
            'duration' => $query->orderBy('days'),
            default => $query->orderByDesc('rating'),
        };
        $packages = $query->get();

        return view('destinations.country', [
            'destination' => $destination,
            'country' => $destination->name,
            'region' => $destination->region,
            'packages' => $packages,
            'months' => $this->searchOptions()['months'],
            'filters' => $filters,
        ]);
    }

    public function deals(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'ship' => ['nullable', 'string', 'max:120'],
            'dep_port' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'in:lt2,2-3.5,3.5-5,gt5'],
            'tab' => ['nullable', Rule::in(['All', 'New user', 'Limited-time', 'Early bird', 'Group discount'])],
        ]);
        $activeDeals = TravelDeal::query()
            ->where('active', true)
            ->whereNotNull('type')
            ->where(function ($query) {
                $query->where('expires_at', '>=', now())
                    ->orWhere(function ($query) {
                        $query->whereNull('expires_at')->where(function ($query) {
                            $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today());
                        });
                    });
            });
        $dealPackages = TravelPackage::query()
            ->where('published', true)
            ->whereIn('id', (clone $activeDeals)->whereNotNull('package_id')->select('package_id'))
            ->get()
            ->keyBy('id');
        $deals = (clone $activeDeals)
            ->with('package')
            ->when(($filters['tab'] ?? 'All') !== 'All', fn ($query) => $query->where('type', $filters['tab']))
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->get()
            ->filter(function (TravelDeal $deal) use ($filters): bool {
                $package = $deal->package;
                if (! $package || ! $package->published) {
                    return false;
                }

                if (! empty($filters['q'])) {
                    $term = mb_strtolower(trim($filters['q']));
                    $searchable = mb_strtolower(implode(' ', [
                        $package->name,
                        $package->description,
                        $package->country,
                        $package->type,
                    ]));
                    if (! str_contains($searchable, $term)) {
                        return false;
                    }
                }
                foreach (['ship' => 'ship', 'dep_port' => 'departure_port'] as $filter => $column) {
                    if (! empty($filters[$filter]) && mb_strtolower((string) $package->{$column}) !== mb_strtolower($filters[$filter])) {
                        return false;
                    }
                }
                if (! empty($filters['month']) && ! collect($package->departures)->contains(fn ($date) => str_starts_with($date, $filters['month']))) {
                    return false;
                }
                if (! empty($filters['price'])) {
                    $price = (float) $package->price_from;
                    $matches = match ($filters['price']) {
                        'lt2' => $price < 2000,
                        '2-3.5' => $price >= 2000 && $price < 3500,
                        '3.5-5' => $price >= 3500 && $price < 5000,
                        'gt5' => $price >= 5000,
                    };
                    if (! $matches) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
        $options = $this->searchOptions($dealPackages->values());

        return view('deals', [
            'deals' => $deals,
            'activeTab' => $filters['tab'] ?? 'All',
            'searchOptions' => $options,
        ]);
    }

    private function searchOptions(?Collection $packages = null): array
    {
        $packages ??= TravelPackage::query()->where('published', true)->get();

        return [
            'months' => collect(range(0, 11))->mapWithKeys(function ($offset) {
                $month = Carbon::now()->startOfMonth()->addMonths($offset);

                return [$month->format('Y-m') => $month->format('F Y')];
            }),
            'ships' => $packages->pluck('ship')->filter()->unique()->sort()->values(),
            'ports' => $packages->pluck('departure_port')->filter()->unique()->sort()->values(),
        ];
    }

    private function numberInWords(int $number): string
    {
        return [
            0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
            6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven',
            12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen',
            17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty',
        ][$number] ?? (string) $number;
    }

    public function ships(): View
    {
        return view('ships', [
            'ships' => Ship::with('cabins')->orderBy('name')->get(),
            'selectedShip' => null,
            'tabs' => [],
            'recentCruises' => collect(),
            'activeTab' => null,
        ]);
    }

    public function ship(string $ship): View
    {
        $selectedShip = Ship::with('cabins')->where('slug', $ship)->firstOrFail();
        $tabs = ['Overview', 'Gallery', 'Deck plans', 'Amenities', 'Cabins', 'Recent cruises'];
        $activeTab = request()->query('tab', 'Overview');
        abort_unless(in_array($activeTab, $tabs, true), 404);
        $recentCruises = TravelPackage::query()
            ->where('published', true)
            ->where('ship', $selectedShip->name)
            ->orderBy('price_from')
            ->get();

        return view('ships', [
            'ships' => Ship::with('cabins')->orderBy('name')->get(),
            'selectedShip' => $selectedShip,
            'tabs' => $tabs,
            'recentCruises' => $recentCruises,
            'activeTab' => $activeTab,
        ]);
    }

    private function fleet(): array
    {
        $ships = [
            ['slug' => 'aurelia-voyager', 'name' => 'Aurelia Voyager', 'class' => 'Grand Class', 'package' => 'med-grand'],
            ['slug' => 'meridian-star', 'name' => 'Meridian Star', 'class' => 'Explorer Class', 'package' => 'reef-cruise'],
            ['slug' => 'coral-horizon', 'name' => 'Coral Horizon', 'class' => 'Boutique Class', 'package' => 'aegean-hopper'],
            ['slug' => 'nordic-dawn', 'name' => 'Nordic Dawn', 'class' => 'Expedition Class', 'package' => 'atlantic-crossing'],
            ['slug' => 'navigator-of-the-seas', 'name' => 'Navigator of the Seas', 'class' => 'Voyager Class', 'package' => 'pacific-crossing'],
        ];

        return collect($ships)->map(function (array $ship) {
            $journey = TravelPackage::where('published', true)->where('slug', $ship['package'])->first();

            return [...$ship, 'journey' => $journey];
        })->filter(fn (array $ship) => $ship['journey'] !== null)->values()->all();
    }

    public function about(): View
    {
        return view('static.about');
    }

    public function faq(): View
    {
        return view('static.faq', ['faqs' => Faq::orderBy('sort_order')->get()]);
    }

    public function policy(string $policy): View
    {
        abort_unless(in_array($policy, ['terms', 'privacy', 'cancellation'], true), 404);

        return view('static.policy', ['policy' => $policy]);
    }

    public function blog(): View
    {
        return view('static.blog', [
            'post' => null,
            'posts' => Guide::where('published', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function blogPost(string $slug): View|RedirectResponse
    {
        $legacySlugs = [
            'slow-travel-in-kyoto' => 'a-slower-way-through-kyoto',
            'when-to-see-the-great-migration' => 'when-to-see-the-mara-river-crossings',
            'amalfi-without-the-rush' => 'the-amalfi-coast-without-the-rush',
        ];
        if (isset($legacySlugs[$slug])) {
            return redirect()->route('blog.show', ['slug' => $legacySlugs[$slug]], 301);
        }
        $post = Guide::where('slug', $slug)->where('published', true)->firstOrFail();

        return view('static.blog', ['post' => $post]);
    }

    public function contact(): View
    {
        return view('static.contact');
    }

    public function sendEnquiry(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', Rule::in(['Booking enquiry', 'Existing booking', 'Group travel', 'Press'])],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with('error', 'Please add your name, a valid email and a message.')
                ->withInput($request->only('name', 'email', 'subject', 'message'));
        }

        Enquiry::create($validator->validated());

        return back()->with('success', 'Message sent. We reply within one working day.');
    }

    public function subscribeNewsletter(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->only('email'), [
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Please enter a valid email',
            'email.email' => 'Please enter a valid email',
            'email.max' => 'Please enter a valid email',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Please enter a valid email')->withInput($request->only('email'));
        }

        $email = Str::lower(trim($validator->validated()['email']));
        NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            ['subscribed_at' => now(), 'unsubscribed_at' => null],
        );

        return back()->with('success', 'You are subscribed. Welcome aboard.');
    }

    public function addToCart(Request $request, TravelPackage $package): RedirectResponse
    {
        $data = $request->validate([
            'departure_date' => ['required', 'date', Rule::in(Pricing::availableDepartureDates($package))],
            'guests' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $cart = $request->session()->get('cart', []);
        $cart[] = [
            'uid' => (string) Str::uuid(),
            'package_id' => $package->id,
            'departure_date' => $data['departure_date'],
            'guests' => $data['guests'],
            'addons' => [],
        ];
        $request->session()->put('cart', $cart);

        return redirect()->route('cart')->with('success', $package->name.' added to your trip.');
    }

    public function toggleWishlist(Request $request, TravelPackage $package): RedirectResponse|JsonResponse
    {
        abort_unless($package->published, 404);

        if (Auth::check()) {
            $changes = Auth::user()->favorites()->toggle($package->id);
            $saved = count($changes['attached']) > 0;
        } else {
            $wishlist = collect($request->session()->get('wishlist', []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $saved = ! $wishlist->contains($package->id);
            $wishlist = $saved
                ? $wishlist->push($package->id)->values()
                : $wishlist->reject(fn ($id) => $id === $package->id)->values();
            $request->session()->put('wishlist', $wishlist->all());
        }

        $message = $saved ? 'Journey saved to your list.' : 'Journey removed from your saved list.';

        if ($request->expectsJson()) {
            return response()->json(['saved' => $saved, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function applyPromo(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->only('code'), ['code' => ['required', 'string', 'max:40']]);
        if ($validator->fails()) {
            return back()->with('error', $this->invalidPromoMessage())->withInput($request->only('code'));
        }
        $data = $validator->validated();
        $promo = Pricing::promoForCode($data['code']);
        if (! $promo) {
            return back()->with('error', $this->invalidPromoMessage())->withInput($request->only('code'));
        }
        if ($promo instanceof PromoCode
            && Pricing::cartTotals($this->cartItems($request), $promo)['discount'] <= 0) {
            return back()
                ->with('error', 'This promo code does not apply to the packages in your cart.')
                ->withInput($request->only('code'));
        }
        $code = $promo instanceof TravelDeal ? $promo->promo_code : $promo->code;
        $request->session()->put('promo_code', $code);

        return back()->with('success', 'Promo code applied');
    }

    public function cart(Request $request): View
    {
        $items = $this->cartItems($request);
        $items = $items->map(function (array $item) {
            $displayDetail = $item['detail'] ?? [];
            foreach ($displayDetail['guests'] ?? [] as &$guest) {
                unset($guest['passport']);
            }
            unset($guest);

            return [...$item, 'display_detail' => $displayDetail];
        });
        $unavailableItems = $items->filter(fn (array $item) => ! Pricing::itemAvailable($item));
        $promo = Pricing::promoForCode($request->session()->get('promo_code'));

        return view('cart', [
            'items' => $items,
            'totals' => $this->cartTotals($items, $request),
            'unavailableItems' => $unavailableItems,
            'promoCode' => $promo instanceof TravelDeal ? $promo->promo_code : $promo?->code,
            'taxRate' => (float) config('travel.tax_rate'),
            'activePromoCount' => Pricing::promos()->count(),
        ]);
    }

    public function updateCartItem(Request $request, string $uid): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        $index = collect($cart)->search(fn (array $item) => ($item['uid'] ?? null) === $uid);
        if ($index === false && ctype_digit($uid)) {
            $index = (int) $uid;
        }
        abort_unless($index !== false && isset($cart[$index]), 404);
        abort_if(! empty($cart[$index]['detail']), 403, 'Wizard bookings must be changed through Rebook with changes.');

        $package = TravelPackage::findOrFail($cart[$index]['package_id']);
        $ship = $package->ship ? Ship::with('cabins')->where('name', $package->ship)->first() : null;
        $data = $request->validate([
            'departure_date' => ['required', 'date', Rule::in(Pricing::availableDepartureDates($package))],
            'cabin' => ['nullable', 'string', Rule::in($ship?->cabins->pluck('type')->all() ?? [])],
            'pax' => ['required', 'integer', 'min:1', 'max:12'],
            'addons' => ['sometimes', 'array'],
            'addons.*' => ['string', 'distinct', Rule::in(collect(Pricing::addons($package))->pluck('id')->all())],
        ]);
        $cart[$index] = [
            ...$cart[$index],
            'departure_date' => $data['departure_date'],
            'guests' => (int) $data['pax'],
            'cabin' => $data['cabin'] ?? null,
            'addons' => array_values($data['addons'] ?? []),
        ];
        $request->session()->put('cart', $cart);

        return redirect()->route('cart')->with('success', 'Trip updated.');
    }

    public function removeFromCart(Request $request, string $uid): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        $index = collect($cart)->search(fn (array $item) => ($item['uid'] ?? null) === $uid);
        if ($index === false && ctype_digit($uid)) {
            $index = (int) $uid;
        }
        if ($index !== false && isset($cart[$index])) {
            unset($cart[$index]);
            $cart = array_values($cart);
            $request->session()->put('cart', $cart);
            if ($cart === []) {
                $request->session()->forget('promo_code');
            }
        }

        return redirect()->route('cart')->with('success', 'Trip removed from your cart.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $items = $this->cartItems($request);
        if ($items->isEmpty()) {
            return redirect()->route('search')->with('error', 'Add a trip to your cart before checking out.');
        }

        $firstDetailGuest = collect($items->first()['detail']['guests'] ?? [])->first() ?? [];
        $traveller = Auth::user()?->traveller_details ?? [];
        if (isset($firstDetailGuest['passport']) && is_string($firstDetailGuest['passport'])) {
            $firstDetailGuest['passport'] = Crypt::decryptString($firstDetailGuest['passport']);
        }
        $promo = Pricing::promoForCode($request->session()->get('promo_code'));

        return view('checkout', [
            'items' => $items,
            'totals' => $this->cartTotals($items, $request),
            'unavailableItems' => $items->filter(fn (array $item) => ! Pricing::itemAvailable($item)),
            'promoCode' => $promo instanceof TravelDeal ? $promo->promo_code : $promo?->code,
            'taxRate' => (float) config('travel.tax_rate'),
            'paymentGateway' => app()->bound(PaymentGateway::class) ? app(PaymentGateway::class) : null,
            'contactDefaults' => [
                'name' => Auth::user()?->name ?? ($firstDetailGuest['name'] ?? ''),
                'email' => Auth::user()?->email ?? ($firstDetailGuest['email'] ?? ''),
                'phone' => Auth::user()?->phone ?? ($firstDetailGuest['phone'] ?? ''),
                'address' => $traveller['billing_address'] ?? '',
            ],
            'travellerDefaults' => array_merge($traveller, [
                'name' => $firstDetailGuest['name'] ?? ($traveller['name_as_per_ic'] ?? ($traveller['name'] ?? Auth::user()?->name ?? '')),
                'dob' => $firstDetailGuest['dob'] ?? ($traveller['date_of_birth'] ?? ($traveller['dob'] ?? '')),
                'nationality' => $firstDetailGuest['nationality'] ?? ($traveller['nationality'] ?? ''),
                'passport_number' => $firstDetailGuest['passport'] ?? ($traveller['passport_number'] ?? ''),
                'passport_expiry' => $firstDetailGuest['passportExpiry'] ?? ($traveller['passport_expiry'] ?? ''),
                'requests' => $traveller['dietary_needs'] ?? ($traveller['requests'] ?? ''),
            ]),
            'activePromoCount' => Pricing::promos()->count(),
            'nations' => ['Malaysian', 'Singaporean', 'Indonesian', 'Thai', 'Chinese', 'Indian', 'Japanese', 'Korean', 'Australian', 'British', 'American', 'Canadian', 'German', 'French'],
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        if ($request->boolean('apply_promo')) {
            $codeValidator = Validator::make($request->only('code'), ['code' => ['required', 'string', 'max:40']]);
            $promo = $codeValidator->fails()
                ? null
                : Pricing::promoForCode($codeValidator->validated()['code']);
            if (! $promo) {
                return back()->with('error', $this->invalidPromoMessage())
                    ->withInput($request->except(['card_number', 'card_expiry', 'card_cvc', 'payment_token']));
            }
            $request->session()->put('promo_code', $promo instanceof TravelDeal ? $promo->promo_code : $promo->code);

            return back()->with('success', 'Promo code applied')
                ->withInput($request->except(['card_number', 'card_expiry', 'card_cvc', 'payment_token']));
        }

        $items = $this->cartItems($request);
        if ($items->isEmpty()) {
            return redirect()->route('search')->with('error', 'Your cart is empty.');
        }
        if ($items->contains(fn (array $item) => ! Pricing::itemAvailable($item))) {
            return redirect()->route('cart')->with('error', 'Update your cart before checking out.');
        }

        $gateway = app()->bound(PaymentGateway::class) ? app(PaymentGateway::class) : null;
        $needsCardInputs = $gateway && request('payment_method', 'card') === 'card' && $gateway->hostedFieldsView() === null;
        $safeInput = $request->except(['card_number', 'card_expiry', 'card_cvc', 'payment_token']);
        $latestDeparture = max($items->pluck('departure_date')->all());
        $minimumExpiry = Carbon::parse($latestDeparture)->addMonthsNoOverflow(6)->startOfDay();
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'contact_address' => ['required', 'string', 'max:500'],
            'traveller_name' => ['required', 'string', 'max:120'],
            'traveller_dob' => ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'nationality' => ['required', 'string', 'max:100'],
            'passport_number' => ['required', 'regex:/^[A-Za-z0-9]{6,12}$/'],
            'passport_expiry' => ['required', 'date', 'after_or_equal:'.$minimumExpiry->toDateString()],
            'requests' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in(['card', 'paypal', 'bank'])],
            'billing_same' => ['nullable', 'boolean'],
            'billing_address' => ['required_unless:billing_same,1', 'nullable', 'string', 'max:500'],
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
            'cancellation' => ['accepted'],
        ];
        if ($needsCardInputs) {
            $rules += [
                'card_number' => ['required', 'regex:/^\d{4}(?: ?\d{4}){3}$/'],
                'card_expiry' => ['required', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
                'card_cvc' => ['required', 'regex:/^\d{3,4}$/'],
                'card_name' => ['required', 'string', 'max:120'],
            ];
        } elseif ($gateway && request('payment_method', 'card') === 'card' && $gateway->hostedFieldsView() !== null) {
            $rules['payment_token'] = ['required', 'string', 'max:2000'];
        }
        $messages = [
            '*.required' => 'Required',
            'email.email' => 'Enter a valid email address',
            'card_number.regex' => 'Card number must be 16 digits',
            'card_cvc.regex' => '3 or 4 digits',
            'terms.accepted' => 'Please accept the Terms & Conditions',
            'privacy.accepted' => 'Please accept the Privacy Policy',
            'cancellation.accepted' => 'Please acknowledge the cancellation policy',
            'passport_expiry.after_or_equal' => 'Passport must be valid for 6 months after departure (on or after '.$minimumExpiry->format('j M Y').')',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($safeInput)
                ->with('error', 'Please fix the highlighted fields');
        }
        $data = $validator->validated();
        if (! empty($data['billing_same'])) {
            $data['billing_address'] = $data['contact_address'];
        }

        $paymentStatus = 'Pending payment';
        if ($gateway) {
            $paymentDetails = $needsCardInputs
                ? $request->only(['card_number', 'card_expiry', 'card_cvc', 'card_name'])
                : ['token' => $request->input('payment_token')];
            $result = $gateway->process($data['payment_method'], $paymentDetails);
            if (! ($result['success'] ?? false)) {
                return redirect()->route('payment.failed');
            }
            $paymentStatus = $result['status'] ?? 'Pending payment';
        }

        $totals = $this->cartTotals($items, $request);
        $orderReference = $this->uniqueOrderReference();
        $discounts = $totals['discount_allocations'];
        $bookings = DB::transaction(function () use ($items, $data, $totals, $orderReference, $paymentStatus, $discounts) {
            $remainingTaxes = $totals['taxes'];

            return $items->values()->map(function ($item, $index) use ($items, $data, $orderReference, $paymentStatus, $discounts, &$remainingTaxes) {
                $subtotal = Pricing::itemTotal($item);
                $isLast = $index === $items->count() - 1;
                $discount = $discounts[$index] ?? 0.0;
                $taxes = $isLast
                    ? $remainingTaxes
                    : round(($subtotal - $discount) * (float) config('travel.tax_rate'), 2);
                $remainingTaxes -= $taxes;
                $detail = $item['detail'] ?? [];
                $travellers = [];
                $passports = [];
                foreach ($detail['guests'] ?? [] as $guest) {
                    $passport = $guest['passport'] ?? null;
                    if (is_string($passport) && $passport !== '') {
                        $passports[] = Crypt::decryptString($passport);
                    }
                    unset($guest['passport']);
                    $travellers[] = $guest;
                }

                return Booking::create([
                    'reference' => 'TWY-'.Str::upper(Str::random(7)),
                    'order_reference' => $orderReference,
                    'user_id' => Auth::id(),
                    'package_id' => $item['package']->id,
                    'departure_date' => $item['departure_date'],
                    'guests' => $item['guests'],
                    'guest_details' => [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'contact_address' => $data['contact_address'],
                        'billing_address' => $data['billing_address'],
                        'traveller' => [
                            'name' => $data['traveller_name'],
                            'dob' => $data['traveller_dob'],
                            'nationality' => $data['nationality'],
                            'passport_expiry' => $data['passport_expiry'],
                            'requests' => $data['requests'] ?? '',
                        ],
                        'travellers' => $travellers,
                        'booking_detail' => array_diff_key($detail, ['guests' => true]),
                        'addons' => $item['addons'] ?? [],
                    ],
                    'traveller_passports' => $passports,
                    'passport_number' => $data['passport_number'],
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'taxes' => $taxes,
                    'total' => round($subtotal - $discount + $taxes, 2),
                    'status' => $paymentStatus,
                    'payment_method' => $data['payment_method'],
                ]);
            });
        });
        $request->session()->forget('cart');
        $request->session()->forget('promo_code');
        $request->session()->put('confirmation_order_reference', $orderReference);
        $recipient = $data['email'];
        $itinerary = $bookings->map(fn (Booking $booking) => $booking->package->name.' — '.fmt_date($booking->departure_date).' · '.$booking->guests.' guests')->implode("\n");
        if (config('mail.default') !== 'log') {
            Mail::raw(
                "Your TravelWithYou booking {$orderReference} has been received.\n\n{$itinerary}",
                fn ($message) => $message->to($recipient)->subject('Your TravelWithYou booking'),
            );
        } else {
            Log::info('TravelWithYou confirmation email queued.', ['order_reference' => $orderReference]);
        }

        return redirect()->route('confirmation');
    }

    public function confirmation(Request $request): View|RedirectResponse
    {
        $orderReference = $request->session()->get('confirmation_order_reference');
        if (! $orderReference && Auth::check()) {
            $orderReference = Booking::where('user_id', Auth::id())->latest('created_at')->value('order_reference');
        }
        $bookings = $orderReference ? Booking::with('package')->where('order_reference', $orderReference)->get() : collect();
        $sessionAuthorized = $request->session()->get('confirmation_order_reference') === $orderReference;
        $userAuthorized = Auth::check() && $bookings->every(fn (Booking $booking) => $booking->user_id === Auth::id());
        if ($bookings->isNotEmpty() && ! $sessionAuthorized && ! $userAuthorized) {
            $bookings = collect();
        }

        return view('confirmation', [
            'bookings' => $bookings,
            'orderReference' => $orderReference,
            'totalPaid' => $bookings->sum('total'),
        ]);
    }

    public function paymentFailed(): View
    {
        return view('payment-failed');
    }

    public function invoice(string $reference): Response
    {
        $bookings = $this->authorizedOrder($reference);
        $pdf = Pdf::loadView('pdf.invoice', [
            'bookings' => $bookings,
            'orderReference' => $reference,
            'totalPaid' => $bookings->sum('total'),
        ]);

        return $pdf->download($reference.'-invoice.pdf');
    }

    private function cartTotals($items, Request $request): array
    {
        return Pricing::cartTotals($items, Pricing::promoForCode($request->session()->get('promo_code')));
    }

    private function cartItems(Request $request)
    {
        $cart = $request->session()->get('cart', []);
        $changed = false;
        foreach ($cart as &$item) {
            if (empty($item['uid'])) {
                $item['uid'] = (string) Str::uuid();
                $changed = true;
            }
        }
        unset($item);
        if ($changed) {
            $request->session()->put('cart', $cart);
        }

        return collect($cart)
            ->map(function ($item, $index) {
                $package = TravelPackage::find($item['package_id']);

                return $package ? [...$item, 'package' => $package, 'index' => $index] : null;
            })
            ->filter()
            ->values();
    }

    private function invalidPromoMessage(): string
    {
        return Pricing::promos()->has('WELCOME10')
            ? 'That code is not valid. Try WELCOME10.'
            : 'That code is not valid.';
    }

    private function uniqueOrderReference(): string
    {
        do {
            $reference = 'TWY-'.random_int(10000, 99999);
        } while (Booking::where('order_reference', $reference)->exists());

        return $reference;
    }

    private function authorizedOrder(string $reference)
    {
        $bookings = Booking::with('package')->where('order_reference', $reference)->get();
        abort_if($bookings->isEmpty(), 404);
        $sessionAuthorized = session('confirmation_order_reference') === $reference;
        $userAuthorized = Auth::check() && $bookings->every(fn (Booking $booking) => $booking->user_id === Auth::id());
        abort_unless($sessionAuthorized || $userAuthorized, 404);

        return $bookings;
    }
}
