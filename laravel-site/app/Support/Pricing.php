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
        ['id' => 'adult', 'label' => 'Adult', 'age' => '12 years and over', 'bed' => 'Shares a twin or double room with another adult', 'factor' => 1],
        ['id' => 'single', 'label' => 'Single', 'age' => '18 years and over, travelling solo', 'bed' => 'Own room, includes a 50% single supplement', 'factor' => 1.5],
        ['id' => 'childBed', 'label' => 'Child with bed', 'age' => '2 to 10 years', 'bed' => "Own extra foldable bed in the parents' room", 'factor' => 0.9],
        ['id' => 'childTwin', 'label' => 'Child twin', 'age' => '2 to 11 years', 'bed' => 'Shares a twin-bed room with one adult', 'factor' => 0.8],
        ['id' => 'childNoBed', 'label' => 'Child no bed', 'age' => '2 to 5 years', 'bed' => 'Shares a bed with a parent, no extra bed', 'factor' => 0.6],
    ];

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
            $shipName = self::value($package, 'ship');
            $cabinPrice = $shipName
                ? Ship::query()->where('name', $shipName)->first()?->cabins()->where('type', $cabin)->value('price_from')
                : null;
            if ($cabinPrice !== null) {
                $base = (float) $cabinPrice;
            }
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

    public static function itemAvailable(array $item): bool
    {
        $package = self::packageFor($item);

        return $package !== null
            && (bool) self::value($package, 'published', true)
            && in_array((string) ($item['date'] ?? $item['departure_date'] ?? ''), self::availableDepartureDates($package), true);
    }

    public static function minRooms(int $adults, int $children): int
    {
        return max(1, (int) ceil(($adults + $children) / self::MAX_PER_ROOM));
    }

    public static function roomError(int $adults, int $children, int $rooms): string
    {
        $need = self::minRooms($adults, $children);
        if ($rooms < $need) {
            return 'Maximum '.self::MAX_PER_ROOM.' guests per room. '.($adults + $children).' guests need at least '.$need.' rooms.';
        }

        if ($rooms > $adults) {
            return 'Each room needs at least 1 adult. '.$adults.' adult'.($adults === 1 ? '' : 's').' can fill at most '.$adults.' room'.($adults === 1 ? '' : 's').'.';
        }

        return '';
    }

    public static function allocateRooms(int $adults, int $children, int $rooms): array
    {
        $allocated = array_fill(0, max(0, $rooms), ['adults' => 0, 'children' => 0]);
        if ($rooms <= 0) {
            return $allocated;
        }

        for ($i = 0; $i < $adults; $i++) {
            $allocated[$i % $rooms]['adults']++;
        }

        for ($i = 0; $i < $children; $i++) {
            $room = 0;
            foreach ($allocated as $index => $candidate) {
                if ($allocated[$room]['adults'] + $allocated[$room]['children'] > $candidate['adults'] + $candidate['children']) {
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
            $shipName = self::value($package, 'ship');
            $ship = $shipName ? Ship::query()->with('cabins')->where('name', $shipName)->first() : null;
            $cheapest = $ship?->cabins->sortBy('price_from')->first()?->type;
            $rooms = $detail['rooms'] ?? [];
            $pax = array_sum(array_map(fn (array $room) => (int) ($room['adults'] ?? 0) + (int) ($room['children'] ?? 0), $rooms));

            foreach ($rooms as $index => $room) {
                $type = $room['type'] ?? '';
                $adultPrice = self::unitPrice($package, $pax, $date, $type !== '' ? $type : $cheapest);
                $name = 'Room '.($index + 1).' · '.($type !== '' ? $type : 'Room type pending');
                $adults = (int) ($room['adults'] ?? 0);
                $children = (int) ($room['children'] ?? 0);

                if ($adults > 0) {
                    $lines[] = [
                        'group' => 'Rooms',
                        'label' => $name.': '.$adults.' adult'.($adults > 1 ? 's' : '').' × '.fmt_rm($adultPrice),
                        'amount' => $adultPrice * $adults,
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
            foreach (self::GUEST_KINDS as $kind) {
                $pax += (int) ($counts[$kind['id']] ?? 0);
            }
            $base = self::unitPrice($package, $pax, $date);
            foreach (self::GUEST_KINDS as $kind) {
                $count = (int) ($counts[$kind['id']] ?? 0);
                if ($count > 0) {
                    $price = $base * $kind['factor'];
                    $lines[] = [
                        'group' => 'Guests',
                        'label' => $kind['label'].' × '.$count.' @ '.fmt_rm($price),
                        'amount' => $price * $count,
                    ];
                }
            }
        }

        foreach ($addons as $addonId) {
            $addon = collect(self::ADDONS)->firstWhere('id', $addonId);
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
            $addon = collect(self::ADDONS)->firstWhere('id', $addonId);
            $addonTotal += (int) ($addon['price'] ?? 0);
        }

        return (self::unitPrice($package, $pax, $date, $item['cabin'] ?? null) + $addonTotal) * $pax;
    }

    public static function cartTotals(iterable $items, PromoCode|TravelDeal|null $promo): array
    {
        $subtotal = 0.0;
        $promoEligibleSubtotal = 0.0;
        foreach ($items as $item) {
            $itemTotal = self::itemTotal($item);
            $subtotal += $itemTotal;
            if (config('travel.stack_promo_on_deals', true) || self::dealFor(self::packageFor($item) ?? '') === null) {
                $promoEligibleSubtotal += $itemTotal;
            }
        }

        $promoPct = $promo ? (float) $promo->discount_percent / 100 : 0.0;
        $discount = round($promoEligibleSubtotal * $promoPct, 2);
        $taxes = round(($subtotal - $discount) * (float) config('travel.tax_rate'), 2);
        $total = round($subtotal - $discount + $taxes, 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'taxes' => $taxes,
            'total' => $total,
            'promo' => $promo,
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
