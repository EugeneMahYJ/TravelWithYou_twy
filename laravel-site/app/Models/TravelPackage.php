<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelPackage extends Model
{
    public const TYPE_FLIGHT_AND_HOTEL = 'Flight + Hotel';

    public const TYPE_CRUISE = 'Cruise';

    public const TRIP_TYPES = [
        self::TYPE_FLIGHT_AND_HOTEL,
        self::TYPE_CRUISE,
    ];

    protected $fillable = [
        'slug',
        'source_id',
        'country_id',
        'name',
        'country',
        'region',
        'type',
        'ship',
        'brand',
        'operator',
        'days',
        'nights',
        'description',
        'long_description',
        'details',
        'highlights',
        'fares',
        'gallery',
        'trip_plan',
        'included',
        'excluded',
        'price_from',
        'price_to',
        'rating',
        'reviews',
        'departure_port',
        'destination_port',
        'departures',
        'destinations',
        'itinerary',
        'source_data',
        'pricing',
        'image',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'departures' => 'array',
            'destinations' => 'array',
            'itinerary' => 'array',
            'source_data' => 'array',
            'pricing' => 'array',
            'highlights' => 'array',
            'fares' => 'array',
            'gallery' => 'array',
            'trip_plan' => 'array',
            'included' => 'array',
            'excluded' => 'array',
            'published' => 'boolean',
            'price_from' => 'integer',
            'price_to' => 'integer',
            'rating' => 'float',
            'nights' => 'integer',
        ];
    }

    public function getTypeAttribute(?string $value): string
    {
        return $value === self::TYPE_CRUISE
            ? self::TYPE_CRUISE
            : self::TYPE_FLIGHT_AND_HOTEL;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'package_id');
    }

    public function countryRecord(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function excluded(): array
    {
        if ($this->getAttribute('excluded') !== null) {
            return $this->getAttribute('excluded');
        }

        if ($this->type === self::TYPE_CRUISE) {
            return [
                'International and domestic flight tickets to and from the embarkation port',
                'Hotel accommodation, transfers and meals before or after the cruise, unless stated in the program',
                'Personal expenses: onboard purchases, laundry, mini bar, spa treatments, photos, internet/Wi-Fi, etc',
                'Specialty restaurants, alcoholic and premium beverages, and additional meals',
                'Shore excursions and optional tours at ports of call, unless stated as included',
                'Gratuities/service charges onboard, unless stated as included',
                'Personal travel insurance',
                'Visa fees',
                'Any item not specifically mentioned in the inclusions',
            ];
        }

        return [
            'Personal expenses such as telephone, minibar, laundry and internet/Wi-Fi',
            'Surcharge for guides speaking other languages',
            'Additional meals',
            'Hotel surcharge during peak season or holidays',
            'Personal travel insurance',
            'Visa fees',
            'Any item not specifically mentioned in the inclusions',
        ];
    }

    public function dailyPlan(): array
    {
        if ($this->trip_plan) {
            return array_map(fn (array $day) => [
                'place' => $day[0] ?? 'On the journey',
                'activities' => $day[1] ?? '',
                'transport' => $day[2] ?? '',
            ], $this->trip_plan);
        }

        return array_map(function (array $day) {
            $route = $day['route'] ?? [];
            $activities = collect($day['activities'] ?? [])
                ->map(fn ($activity) => is_array($activity)
                    ? trim(($activity['name'] ?? $activity['activity'] ?? '').(! empty($activity['note']) ? ' — '.$activity['note'] : ''))
                    : (string) $activity)
                ->filter()
                ->values();

            if (! empty($day['meals'])) {
                $activities->push('Meals: '.implode(', ', $day['meals']));
            }
            if (! empty($day['distance_km'])) {
                $activities->push('Distance: '.implode(', ', array_map(
                    fn ($distance, $routeName) => $routeName.': '.$distance.' km',
                    array_values($day['distance_km']),
                    array_keys($day['distance_km']),
                )));
            }
            if (! empty($day['drive_time'])) {
                $activities->push('Drive time: '.implode(', ', array_map(
                    fn ($duration, $routeName) => $routeName.': '.$duration,
                    array_values($day['drive_time']),
                    array_keys($day['drive_time']),
                )));
            }

            return [
                'place' => $day['title'] ?? $day['port'] ?? (is_array($route) ? implode(' → ', $route) : 'On the journey'),
                'activities' => $activities->implode(' · '),
                'transport' => $day['transport'] ?? (! empty($route) ? 'Route: '.implode(' → ', $route) : ''),
            ];
        }, $this->itinerary ?? []);
    }

    public function included(): array
    {
        if ($this->getAttribute('included') !== null) {
            return $this->getAttribute('included');
        }

        if ($this->type === self::TYPE_CRUISE) {
            return [
                'Cruise accommodation in the selected cabin category',
                'Onboard meals as specified by the cruise line',
                'Entertainment, shows and onboard activities as scheduled by the cruise line',
                'Use of standard onboard facilities, subject to operating hours and availability',
                'Cruise itinerary as stated in the program',
            ];
        }

        $included = [
            'International flight ticket',
            'Local tour guide during the tour',
            'Accommodation at the hotels listed or similar, based on twin/double sharing',
            'Daily breakfast, halal lunch and dinner as stated in the programme.(B: International breakfast buffet, L: Lunch, D: Dinner)',
            'Shows and sightseeing as scheduled',
            'Transportation by private air-conditioned vehicle',
            'Daily drinking water for each guest',
            'Guide and driver tipping',
        ];
    }
}
