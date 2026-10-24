<?php

namespace App\Support;

use App\Models\AdminNotificationRead;
use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\TravelDeal;
use App\Models\TravelPackage;
use Carbon\Carbon;

class AdminNotifications
{
    public const KEYS = [
        'packages.expired',
        'deals.expired_active',
        'packages.no_departures',
        'enquiries.unanswered',
        'bookings.pending_payment',
        'packages.expiring_soon',
        'deals.ending_soon',
        'bookings.new_24h',
    ];

    private ?array $items = null;

    private ?array $readRows = null;

    public function items(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $today = today();
        $packages = TravelPackage::query()
            ->where('published', true)
            ->get(['id', 'departures']);
        $expiredPackageIds = [];
        $packagesWithoutFutureDepartureIds = [];
        $expiringPackageIds = [];
        $latestExpiredDate = null;
        $nextPackageDeparture = null;

        foreach ($packages as $package) {
            $dates = collect($package->departures ?? [])
                ->map(fn ($date) => Carbon::parse($date)->startOfDay())
                ->sort()
                ->values();
            $futureDates = $dates->filter(fn (Carbon $date) => $date->gte($today));
            $lastDeparture = $dates->last();
            $nextDeparture = $futureDates->first();

            if ($lastDeparture && $lastDeparture->lt($today)) {
                $expiredPackageIds[] = $package->id;
                if (! $latestExpiredDate || $lastDeparture->gt($latestExpiredDate)) {
                    $latestExpiredDate = $lastDeparture;
                }
            }

            if (! $nextDeparture) {
                $packagesWithoutFutureDepartureIds[] = $package->id;
            } elseif ($nextDeparture->lte($today->copy()->addDays(7)->endOfDay())) {
                $expiringPackageIds[] = $package->id;
                if (! $nextPackageDeparture || $nextDeparture->lt($nextPackageDeparture)) {
                    $nextPackageDeparture = $nextDeparture;
                }
            }
        }

        $now = now();
        $activeDeals = TravelDeal::query()
            ->where('active', true)
            ->get(['id', 'expires_at', 'ends_at']);
        $endedDealIds = [];
        $dealsEndingSoonIds = [];
        $latestEndedDeal = null;
        $nextDealEnding = null;

        foreach ($activeDeals as $deal) {
            $endDate = $deal->expires_at ?? ($deal->ends_at ? Carbon::parse($deal->ends_at)->endOfDay() : null);
            if (! $endDate) {
                continue;
            }

            if ($endDate->lt($now)) {
                $endedDealIds[] = $deal->id;
                if (! $latestEndedDeal || $endDate->gt($latestEndedDeal)) {
                    $latestEndedDeal = $endDate;
                }
            } elseif ($endDate->lte($now->copy()->addHours(48))) {
                $dealsEndingSoonIds[] = $deal->id;
                if (! $nextDealEnding || $endDate->lt($nextDealEnding)) {
                    $nextDealEnding = $endDate;
                }
            }
        }

        $unansweredEnquiryIds = Enquiry::query()
            ->whereIn('status', ['New', 'In progress'])
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $pendingPaymentIds = Booking::query()
            ->where('status', 'Pending payment')
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $recentBookings = Booking::query()
            ->where('created_at', '>=', $now->copy()->subDay())
            ->orderBy('id')
            ->get(['id', 'created_at']);
        $latestNewBookingAt = $recentBookings->max('created_at');
        $latestNewBookingAt = $latestNewBookingAt ? Carbon::parse($latestNewBookingAt) : null;

        $this->items = array_values(array_filter([
            $this->item(
                'critical',
                'packages.expired',
                $expiredPackageIds,
                count($expiredPackageIds),
                count($expiredPackageIds).' packages have expired',
                $latestExpiredDate ? 'Last departure was '.$latestExpiredDate->format('j M Y').'.' : 'Published packages have passed their last departure.',
                route('admin.packages', ['status' => 'Expiring']),
                $latestExpiredDate?->diffForHumans(),
            ),
            $this->item(
                'critical',
                'deals.expired_active',
                $endedDealIds,
                count($endedDealIds),
                count($endedDealIds).' deals ended but are still active',
                $latestEndedDeal ? 'Latest end date was '.$latestEndedDeal->format('j M Y').'.' : 'Active deals have passed their end date.',
                route('admin.deals'),
                $latestEndedDeal?->diffForHumans(),
            ),
            $this->item(
                'critical',
                'packages.no_departures',
                $packagesWithoutFutureDepartureIds,
                count($packagesWithoutFutureDepartureIds),
                count($packagesWithoutFutureDepartureIds).' published packages have no future departures',
                'Add a future departure or unpublish the package.',
                route('admin.packages', ['status' => 'Expiring']),
            ),
            $this->item(
                'warning',
                'enquiries.unanswered',
                $unansweredEnquiryIds,
                count($unansweredEnquiryIds),
                count($unansweredEnquiryIds).' enquiries waiting for a reply',
                'Review unanswered traveller messages.',
                route('admin.enquiries', ['status' => 'Unanswered']),
            ),
            $this->item(
                'warning',
                'bookings.pending_payment',
                $pendingPaymentIds,
                count($pendingPaymentIds),
                count($pendingPaymentIds).' bookings pending payment',
                'Review bookings awaiting payment.',
                route('admin.bookings', ['status' => 'Pending payment']),
            ),
            $this->item(
                'warning',
                'packages.expiring_soon',
                $expiringPackageIds,
                count($expiringPackageIds),
                count($expiringPackageIds).' packages have departures within 7 days',
                $nextPackageDeparture ? 'Next departure is '.$nextPackageDeparture->format('j M Y').'.' : 'Published packages have departures coming up soon.',
                route('admin.packages', ['status' => 'Expiring']),
                $nextPackageDeparture?->diffForHumans(),
            ),
            $this->item(
                'warning',
                'deals.ending_soon',
                $dealsEndingSoonIds,
                count($dealsEndingSoonIds),
                count($dealsEndingSoonIds).' deals end within 48 hours',
                $nextDealEnding ? 'Next end date is '.$nextDealEnding->format('j M Y, H:i').'.' : 'Active deals are ending soon.',
                route('admin.deals'),
                $nextDealEnding?->diffForHumans(),
            ),
            $this->item(
                'info',
                'bookings.new_24h',
                $recentBookings->pluck('id')->all(),
                $recentBookings->count(),
                $recentBookings->count().' new bookings in the last 24 hours',
                'New booking activity in the past day.',
                route('admin.bookings'),
                $latestNewBookingAt?->diffForHumans(),
            ),
        ], fn (?array $item): bool => $item !== null));

        return $this->items;
    }

    public function itemsForAdmin(?int $userId = null): array
    {
        $userId ??= auth()->id();
        $readRows = $this->readRowsFor($userId);
        $items = array_map(function (array $item) use ($readRows): array {
            $item['is_read'] = isset($readRows[$item['key']])
                && hash_equals($readRows[$item['key']], $item['fingerprint']);

            return $item;
        }, $this->items());

        usort($items, fn (array $a, array $b): int => ($a['is_read'] <=> $b['is_read']));

        return $items;
    }

    public function unreadBadgeCount(?int $userId = null): int
    {
        return collect($this->itemsForAdmin($userId))
            ->where('is_read', false)
            ->whereIn('severity', ['critical', 'warning'])
            ->sum('count');
    }

    public function unreadItemCount(?int $userId = null): int
    {
        return collect($this->itemsForAdmin($userId))
            ->where('is_read', false)
            ->count();
    }

    public function attentionCount(): int
    {
        return collect($this->items())
            ->whereIn('severity', ['critical', 'warning'])
            ->sum('count');
    }

    public function countFor(string $key): int
    {
        $item = collect($this->items())->firstWhere('key', $key);

        return (int) ($item['count'] ?? 0);
    }

    public function forgetCachedData(): void
    {
        $this->items = null;
        $this->readRows = null;
    }

    public static function knownKeys(): array
    {
        return self::KEYS;
    }

    private function readRowsFor(?int $userId): array
    {
        if ($this->readRows !== null) {
            return $this->readRows;
        }

        if (! $userId) {
            return $this->readRows = [];
        }

        return $this->readRows = AdminNotificationRead::query()
            ->where('user_id', $userId)
            ->pluck('fingerprint', 'key')
            ->all();
    }

    private function item(
        string $severity,
        string $key,
        array $ids,
        int $count,
        string $title,
        string $detail,
        string $url,
        ?string $time = null,
    ): ?array {
        if ($count === 0) {
            return null;
        }

        sort($ids, SORT_NUMERIC);
        $fingerprint = sha1(json_encode(array_slice($ids, 0, 500), JSON_THROW_ON_ERROR));

        return compact('severity', 'key', 'fingerprint', 'count', 'title', 'detail', 'url', 'time');
    }
}
