<?php

namespace App\Support;

use App\Models\PromoCode;
use App\Models\Ship;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class Pricing
{
    public const CHILD_RATE = 0.75;

    public const MAX_PER_ROOM = 4;

    public const ADDONS = [
        ['id' => 'insurance', 'label' => 'Travel insurance', 'price' => 89],
        ['id' => 'transfer', 'label' => 'Private airport transfers', 'price' => 60],
        ['id' => 'excursion', 'label' => 'Premium excursion pass', 'price' => 140],
    ];

    public const GUEST_KINDS = [
        ['id' => 'adult', 'label' => 'Adult', 'age' => '12 years and over', 'bed' => 'Shares a twin or double room with another adult', 'factor' => 1, 'role' => 'adult'],
        ['id' => 'single', 'label' => 'Single', 'age' => '18 years and over, travelling solo', 'bed' => 'Own room, includes a 50% single supplement', 'factor' => 1.5, 'role' => 'adult'],
        ['id' => 'childBed', 'label' => 'Child with bed', 'age' => '2 to 10 years', 'bed' => "Own extra foldable bed in the parents' room", 'factor' => 0.9, 'role' => 'child'],
        ['id' => 'childTwin', 'label' => 'Child twin', 'age' => '2 to 11 years', 'bed' => 'Shares a twin-bed room with one adult', 'factor' => 0.8, 'role' => 'child'],
        ['id' => 'childNoBed', 'label' => 'Child no bed', 'age' => '2 to 5 years', 'bed' => 'Shares a bed with a parent, no extra bed', 'factor' => 0.6, 'role' => 'child'],
    ];

    public static function guestKinds(TravelPackage|array $package): array
    {
        $configured = self::value($package, 'pricing', [])['guest_types'] ?? [];

        return $configured !== [] ? $configured : self::GUEST_KINDS;
    }

    public static function addons(TravelPackage|array $package): array
    {
        $configured = self::value($package, 'pricing', [])['addons'] ?? [];

        return $configured !== [] ? $configured : self::ADDONS;
    }

    public static function cabinPrices(TravelPackage|array $package): array
    {
        $prices = self::value($package, 'pricing', [])['cabin_prices'] ?? [];
        if ($prices !== []) {
            return $prices;
        }

        $shipName = self::value($package, 'ship');

        return $shipName
            ? Ship::query()->where('name', $shipName)->first()?->cabins()->pluck('price_from', 'type')->map(fn ($price) => (int) $price)->all() ?? []
            : [];
    }

    public static function dealFor(TravelPackage|string|int $package): ?TravelDeal
    {
        if (! $package instanceof TravelPackage) {
            $package = TravelPackage::query()
                ->where('slug', (string) $package)
                ->orWhere('id', is_numeric($package) ? (int) $package : 0)
                ->first();
        }

        if (! $package) {
            return null;
        }

        return TravelDeal::query()
            ->where('package_id', $package->id)
            ->whereNotNull('type')
            ->where('active', true)
            ->where(function ($query) {
                $query->where('expires_at', '>=', now())
                    ->orWhere(function ($query) {
                        $query->whereNull('expires_at')
                            ->where(function ($query) {
                                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today());
                            });
                    });
            })
            ->first();
    }

    public static function fareFor(TravelPackage|array $package, string $date): ?array
    {
        $fares = self::value($package, 'fares', []);
        foreach ($fares ?? [] as $fare) {
            if (($fare['date'] ?? null) === $date) {
                return $fare;
            }
        }

        return null;
    }

    public static function fareUnitPrice(TravelPackage|array $package, array $fare): float
    {
        return (float) $fare['price'] / (($fare['bogo'] ?? false) ? 2 : 1);
    }

    public static function availableDepartureDates(TravelPackage|array $package): array
    {
        return collect(self::value($package, 'departures', []))
            ->merge(collect(self::value($package, 'fares', []))->pluck('date'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function unitPrice(
        TravelPackage|array $package,
        int $pax = 1,
        ?string $date = null,
        ?string $cabin = null
    ): float {
        $base = (float) self::value($package, 'price_from', 0);

        if ($cabin !== null && $cabin !== '') {
            $base = (float) (self::cabinPrices($package)[$cabin] ?? $base);
        } elseif ($date !== null) {
            $fare = self::fareFor($package, $date);
            if ($fare !== null) {
                $base = self::fareUnitPrice($package, $fare);
            }
        }

        $deal = self::dealFor($package instanceof TravelPackage ? $package : (string) ($package['id'] ?? $package['slug'] ?? ''));
        if (! $deal || ($deal->type === 'Group discount' && $pax < 6)) {
            return $base;
        }

        return $base * (1 - (float) $deal->discount_percent / 100);
    }

    public static function guestUnitPrice(
        TravelPackage|array $package,
        string $date,
        array $guestKind,
        int $pax = 1,
        ?int $paxNumber = null
    ): float
    {
        $pricing = self::value($package, 'pricing', []);
        $configuredPrice = $paxNumber === null
            ? null
            : self::paxTierPrice($package, $date, $guestKind['id'], $paxNumber);
        $configuredPrice ??= $pricing['departure_prices'][$date][$guestKind['id']] ?? null;
        if ($configuredPrice !== null) {
            $base = (float) $configuredPrice;
        } else {
            $fare = self::fareFor($package, $date);
            $baseFare = $fare === null ? (float) self::value($package, 'price_from', 0) : self::fareUnitPrice($package, $fare);
            $base = $baseFare * (float) ($guestKind['factor'] ?? 1);
        }

        $deal = self::dealFor($package instanceof TravelPackage ? $package : (string) ($package['id'] ?? $package['slug'] ?? ''));
        if ($deal && ! ($deal->type === 'Group discount' && $pax < 6)) {
            $base *= 1 - (float) $deal->discount_percent / 100;
        }

        return $base;
    }

    public static function paxPriceTiers(TravelPackage|array $package, string $date): array
    {
        return collect(self::value($package, 'pricing', [])['departure_pax_tiers'][$date] ?? [])
            ->filter(fn ($tier) => is_array($tier)
                && (int) ($tier['from'] ?? 0) > 0
                && (int) ($tier['to'] ?? 0) >= (int) ($tier['from'] ?? 1)
                && ! empty($tier['prices']))
            ->sortBy(fn ($tier) => (int) $tier['from'])
            ->values()
            ->all();
    }

    private static function paxTierPrice(TravelPackage|array $package, string $date, string $guestType, int $paxNumber): mixed
    {
        foreach (self::paxPriceTiers($package, $date) as $tier) {
            $from = (int) $tier['from'];
            $to = isset($tier['to']) && $tier['to'] !== '' ? (int) $tier['to'] : PHP_INT_MAX;
            if ($paxNumber >= $from && $paxNumber <= $to && isset($tier['prices'][$guestType])) {
                return $tier['prices'][$guestType];
            }
        }

        return null;
    }

    public static function itemAvailable(array $item): bool
    {
        $package = self::packageFor($item);

        return $package !== null
            && (bool) self::value($package, 'published', true)
            && in_array((string) ($item['date'] ?? $item['departure_date'] ?? ''), self::availableDepartureDates($package), true);
    }

    public static function minRooms(int $adults, int $children, int $singles = 0): int
    {
        $sharingGuests = $adults + $children;
        $sharingRooms = $sharingGuests > 0
            ? max(1, (int) ceil($sharingGuests / self::MAX_PER_ROOM))
            : 0;

        return max(1, $singles + $sharingRooms);
    }

    public static function roomError(int $adults, int $children, int $rooms, int $singles = 0): string
    {
        if ($children > 0 && $adults === 0) {
            return 'Children must share a room with an Adult; Single guests stay in their own room.';
        }

        $need = self::minRooms($adults, $children, $singles);
        if ($rooms < $need) {
            $guests = $adults + $children + $singles;
            $singleNote = $singles > 0
                ? ' (including '.$singles.' private single room'.($singles === 1 ? '' : 's').')'
                : '';

            return 'Maximum '.self::MAX_PER_ROOM.' guests per room. '.$guests.' guests need at least '.$need.' rooms'.$singleNote.'.';
        }

        if ($rooms > $adults + $singles) {
            if ($singles > 0) {
                $adultsCanFill = $adults + $singles;

                return 'Each room needs at least 1 Adult or Single guest. '.$adults.' adult'.($adults === 1 ? '' : 's').' and '.$singles.' single guest'.($singles === 1 ? '' : 's').' can fill at most '.$adultsCanFill.' rooms.';
            }

            return 'Each room needs at least 1 adult. '.$adults.' adult'.($adults === 1 ? '' : 's').' can fill at most '.$adults.' room'.($adults === 1 ? '' : 's').'.';
        }

        return '';
    }

    public static function allocateRooms(int $adults, int $children, int $rooms, int $singles = 0): array
    {
        $allocated = array_fill(0, max(0, $rooms), ['adults' => 0, 'children' => 0]);
        if ($rooms <= 0) {
            return $allocated;
        }

        $singles = min($singles, $rooms);
        for ($i = 0; $i < $singles; $i++) {
            $allocated[$i]['singles'] = 1;
        }

        $sharingRoomCount = $rooms - $singles;
        if ($sharingRoomCount <= 0) {
            return $allocated;
        }

        for ($i = 0; $i < $adults; $i++) {
            $allocated[$singles + ($i % $sharingRoomCount)]['adults']++;
        }

        for ($i = 0; $i < $children; $i++) {
            $room = $singles;
            for ($index = $singles + 1; $index < $rooms; $index++) {
                if ($allocated[$room]['adults'] + $allocated[$room]['children'] > $allocated[$index]['adults'] + $allocated[$index]['children']) {
                    $room = $index;
                }
            }
            $allocated[$room]['children']++;
        }

        return $allocated;
    }

    public static function bookingLines(
        TravelPackage|array $package,
        string $date,
        array $detail,
        array $addons
    ): array {
        $lines = [];
        $pax = 0;

        if (($detail['kind'] ?? null) === 'cruise') {
            $prices = self::cabinPrices($package);
            $cheapest = collect($prices)->sort()->keys()->first();
            $rooms = $detail['rooms'] ?? [];
            $pax = array_sum(array_map(fn (array $room) => (int) ($room['adults'] ?? 0) + (int) ($room['children'] ?? 0) + (int) ($room['singles'] ?? 0), $rooms));
            $singleKind = collect(self::guestKinds($package))->firstWhere('id', 'single');
            $singleFactor = (float) ($singleKind['factor'] ?? 1.5);

            foreach ($rooms as $index => $room) {
                $type = $room['type'] ?? '';
                $adultPrice = self::unitPrice($package, $pax, $date, $type !== '' ? $type : $cheapest);
                $name = 'Room '.($index + 1).' · '.($type !== '' ? $type : 'Room type pending');
                $adults = (int) ($room['adults'] ?? 0);
                $children = (int) ($room['children'] ?? 0);
                $singles = (int) ($room['singles'] ?? 0);

                if ($adults > 0) {
                    $lines[] = [
                        'group' => 'Rooms',
                        'label' => $name.': '.$adults.' adult'.($adults > 1 ? 's' : '').' × '.fmt_rm($adultPrice),
                        'amount' => $adultPrice * $adults,
                    ];
                }
                if ($singles > 0) {
                    $singlePrice = $adultPrice * $singleFactor;
                    $lines[] = [
                        'group' => 'Rooms',
                        'label' => $name.': '.$singles.' single'.($singles > 1 ? 's' : '').' (private room) × '.fmt_rm($singlePrice),
                        'amount' => $singlePrice * $singles,
                    ];
                }
                if ($children > 0) {
                    $childPrice = $adultPrice * self::CHILD_RATE;
                    $lines[] = [
                        'group' => 'Guests',
                        'label' => $name.': '.$children.' child'.($children > 1 ? 'ren' : '').' × '.fmt_rm($childPrice),
                        'amount' => $childPrice * $children,
                    ];
                }
            }
        } else {
            $counts = $detail['counts'] ?? [];
            $guestKinds = self::guestKinds($package);
            $paxTiers = self::paxPriceTiers($package, $date);
            foreach ($guestKinds as $kind) {
                $pax += (int) ($counts[$kind['id']] ?? 0);
            }
            $paxNumber = 0;
            foreach ($guestKinds as $kind) {
                $count = (int) ($counts[$kind['id']] ?? 0);
                $kindLines = [];
                for ($index = 0; $index < $count; $index++) {
                    $paxNumber++;
                    $price = self::guestUnitPrice($package, $date, $kind, $pax, $paxNumber);
                    $tier = collect($paxTiers)->first(fn ($tier) =>
                        $paxNumber >= (int) $tier['from']
                        && $paxNumber <= (isset($tier['to']) && $tier['to'] !== '' ? (int) $tier['to'] : PHP_INT_MAX)
                        && isset($tier['prices'][$kind['id']])
                    );
                    $tierLabel = $tier
                        ? ' (Pax '.$tier['from'].'–'.($tier['to'] ?? '12+').')'
                        : '';
                    $key = $tierLabel.'|'.$price;
                    $kindLines[$key] ??= ['label' => $kind['label'].$tierLabel, 'price' => $price, 'count' => 0];
                    $kindLines[$key]['count']++;
                }
                foreach ($kindLines as $line) {
                    $lines[] = [
                        'group' => 'Guests',
                        'label' => $line['label'].' × '.$line['count'].' @ '.fmt_rm($line['price']),
                        'amount' => $line['price'] * $line['count'],
                    ];
                }
            }
        }

        foreach ($addons as $addonId) {
            $addon = collect(self::addons($package))->firstWhere('id', $addonId);
            if ($addon && $pax > 0) {
                $lines[] = [
                    'group' => 'Add-ons',
                    'label' => $addon['label'].' × '.$pax.' @ '.fmt_rm($addon['price']),
                    'amount' => $addon['price'] * $pax,
                ];
            }
        }

        return $lines;
    }

    public static function itemTotal(array $item): float
    {
        $package = self::packageFor($item);
        if (! $package) {
            throw new InvalidArgumentException('The cart item does not reference an available travel package.');
        }

        $date = (string) ($item['date'] ?? $item['departure_date'] ?? '');
        $addons = $item['addons'] ?? [];
        if (! empty($item['detail'])) {
            return array_sum(array_column(self::bookingLines($package, $date, $item['detail'], $addons), 'amount'));
        }

        $pax = (int) ($item['pax'] ?? $item['guests'] ?? 1);
        $addonTotal = 0;
        foreach ($addons as $addonId) {
            $addon = collect(self::addons($package))->firstWhere('id', $addonId);
            $addonTotal += (int) ($addon['price'] ?? 0);
        }

        return (self::unitPrice($package, $pax, $date, $item['cabin'] ?? null) + $addonTotal) * $pax;
    }

    public static function cartTotals(iterable $items, PromoCode|TravelDeal|null $promo): array
    {
        $subtotal = 0.0;
        $promoEligibleSubtotal = 0.0;
        $eligibleLineTotals = [];
        $eligiblePackageIds = $promo instanceof PromoCode && ! $promo->all_packages
            ? $promo->packages()->pluck('travel_packages.id')->map(fn ($id) => (int) $id)->all()
            : null;
        foreach ($items as $item) {
            $package = self::packageFor($item);
            $itemTotal = self::itemTotal($item);
            $subtotal += $itemTotal;
            $eligibleForDeals = config('travel.stack_promo_on_deals', true)
                || self::dealFor($package ?? '') === null;
            $eligibleForCode = ! $promo instanceof PromoCode || $promo->all_packages
                || in_array((int) self::value($package ?? [], 'id', 0), $eligiblePackageIds ?? [], true);
            if ($eligibleForDeals && $eligibleForCode) {
                $promoEligibleSubtotal += $itemTotal;
                $eligibleLineTotals[] = $itemTotal;
            } else {
                $eligibleLineTotals[] = 0.0;
            }
        }

        $discount = 0.0;
        if ($promo instanceof PromoCode && $promo->discount_type === 'fixed') {
            $discount = min((float) $promo->discount_amount, $promoEligibleSubtotal);
        } elseif ($promo) {
            $promoPct = (float) $promo->discount_percent / 100;
            $discount = round($promoEligibleSubtotal * $promoPct, 2);
        }

        $discountAllocations = array_fill(0, count($eligibleLineTotals), 0.0);
        $eligibleIndexes = array_keys(array_filter($eligibleLineTotals, fn (float $lineTotal) => $lineTotal > 0));
        if ($eligibleIndexes !== [] && $promoEligibleSubtotal > 0) {
            $allocated = 0.0;
            foreach ($eligibleIndexes as $index) {
                if ($index === end($eligibleIndexes)) {
                    $discountAllocations[$index] = round($discount - $allocated, 2);
                } else {
                    $discountAllocations[$index] = round($discount * ($eligibleLineTotals[$index] / $promoEligibleSubtotal), 2);
                    $allocated += $discountAllocations[$index];
                }
            }
        }

        $taxes = round(($subtotal - $discount) * (float) config('travel.tax_rate'), 2);
        $total = round($subtotal - $discount + $taxes, 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'taxes' => $taxes,
            'total' => $total,
            'promo' => $promo,
            'discount_allocations' => $discountAllocations,
        ];
    }

    public static function promoForCode(?string $code): PromoCode|TravelDeal|null
    {
        if (! $code) {
            return null;
        }

        $normalized = mb_strtoupper(trim($code));
        $promo = PromoCode::query()
            ->whereRaw('upper(code) = ?', [$normalized])
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->first();

        return $promo ?? TravelDeal::query()
            ->whereRaw('upper(promo_code) = ?', [$normalized])
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->first();
    }

    public static function promos(): Collection
    {
        return PromoCode::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (PromoCode $promo) => [$promo->code => (float) $promo->discount_percent / 100]);
    }

    private static function packageFor(array $item): TravelPackage|array|null
    {
        if (($item['package'] ?? null) instanceof TravelPackage || is_array($item['package'] ?? null)) {
            return $item['package'];
        }

        $id = $item['pkgId'] ?? $item['package_id'] ?? null;
        if ($id === null) {
            return null;
        }

        return TravelPackage::query()
            ->where('slug', (string) $id)
            ->orWhere('id', is_numeric($id) ? (int) $id : 0)
            ->first();
    }

    private static function value(TravelPackage|array $package, string $key, mixed $default = null): mixed
    {
        return $package instanceof TravelPackage ? ($package->getAttribute($key) ?? $default) : ($package[$key] ?? $default);
    }
}
