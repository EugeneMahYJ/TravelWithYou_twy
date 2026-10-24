<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelPackage extends Model
{
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

        if ($this->type === 'Cruise') {
            return [
                'International flights to and from the ports',
                'Visas and passport fees',
                'Travel insurance (available as an add-on)',
                'Drinks packages and specialty dining beyond credit',
                'Gratuities (suggested RM 16 per guest per day)',
                'Spa treatments and personal expenses',
            ];
        }

        return [
            'International flights to and from the start point',
            'Visas and passport fees',
            'Travel insurance (available as an add-on)',
            'Meals not mentioned in the itinerary',
            'Personal expenses and gratuities',
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

        if ($this->type === 'Cruise') {
            return [
                'Cabin accommodation in your chosen category',
                'Full-board dining in main restaurants',
                'Onboard entertainment, lectures and activities',
                'Port taxes and fees',
                'Shore excursions as listed',
                '24/7 support line during your trip',
            ];
        }

        $included = [
            'Accommodation as listed in the itinerary',
            'Daily breakfast',
            'English-speaking local guides',
            'Ground transport listed in the itinerary',
            'Entrance fees for listed sightseeing',
            '24/7 support line during your trip',
        ];
        if ($this->type === 'Wildlife') {
            return [...$included, 'Park and conservancy fees', 'Daily game drives', 'Internal flights listed'];
        }

        return [...$included, 'Airport arrival and departure transfers on day 1 and last day'];
    }
}
