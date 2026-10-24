<?php

namespace Database\Seeders;

use App\Models\TravelPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class TourPackageSeeder extends Seeder
{
    private const PRICING_AND_DEPARTURES = [
        'JPD-12' => [4990, 6490, ['2026-12-10', '2027-01-14', '2027-02-11', '2027-03-18']],
        'CUS-6' => [3290, 3990, ['2026-11-12', '2026-12-03', '2027-03-25', '2027-04-08']],
        '10XBI' => [6990, 8490, ['2026-11-05', '2027-03-11', '2027-04-15', '2027-05-06']],
        'PECZ-3' => [11990, 13990, ['2027-04-22', '2027-05-20', '2027-09-23']],
        'AMA-DANUBE-LNY-2027' => [11986, 23421, ['2027-01-31']],
        'OCE-SIRENA-MAR2027' => [8866, 15444, ['2027-03-03']],
        'OCE-RIVIERA-APR2027' => [17248, 28512, ['2027-04-05']],
        'OCE-RIVIERA-MAY2027-ALASKA' => [10296, 19932, ['2027-05-24']],
        'OCE-RIVIERA-MAY2027-WONDER' => [11352, 21912, ['2027-05-13']],
        'SILV-ENDEAVOUR-JUL2027' => [74580, 80300, ['2027-06-30']],
        'SILV-SPIRIT-MAY2027' => [39160, 52360, ['2027-05-21']],
        'SILV-SPIRIT-JUN2027' => [39160, 55880, ['2027-06-05']],
        'STAR-VOYAGER-PKL-2026' => [869, 4619, ['2026-10-26', '2026-11-02', '2026-11-09', '2026-11-16']],
    ];

    private const CRUISE_REGIONS = [
        'AMA-DANUBE-LNY-2027' => 'Europe',
        'OCE-SIRENA-MAR2027' => 'Europe',
        'OCE-RIVIERA-APR2027' => 'Asia',
        'OCE-RIVIERA-MAY2027-ALASKA' => 'North America',
        'OCE-RIVIERA-MAY2027-WONDER' => 'North America',
        'SILV-ENDEAVOUR-JUL2027' => 'Arctic',
        'SILV-SPIRIT-MAY2027' => 'Europe',
        'SILV-SPIRIT-JUN2027' => 'Europe',
        'STAR-VOYAGER-PKL-2026' => 'Asia',
    ];

    private const TYPES = [
        'guided_land_tour' => 'Guided tour',
        'river_cruise' => 'River cruise',
        'ocean_cruise' => 'Ocean cruise',
        'expedition_cruise' => 'Expedition cruise',
        'short_cruise' => 'Short cruise',
    ];

    private const IMAGES = [
        '1476610182048-b716b8518aae',
        '1496588152823-86ff7695e68f',
        '1526392060635-9d6019884377',
        '1535940360221-641a69c43bac',
        '1555400038-63f5ba517a47',
        '1558870832-c8db4b5b47d1',
        '1563245372-f21724e3856d',
        '1580502304784-8985b7eb7260',
        '1583844056361-4418a8f2a985',
        '1612698093158-e07ac200d44e',
        '1614095557130-2f4a9e1da57d',
        '1624138784614-87fd1b6528f8',
        '1624253321171-1be53e12f5f4',
    ];

    public function run(): void
    {
        $path = __DIR__.'/data/tour_packages.json';
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data['packages'] as $index => $record) {
            [$priceFrom, $priceTo, $departures] = self::PRICING_AND_DEPARTURES[$record['id']]
                ?? throw new RuntimeException("Missing price and departure mapping for tour {$record['id']}.");

            if (TravelPackage::where('source_id', $record['id'])->exists()) {
                continue;
            }

            $duration = $record['duration'] ?? [];
            $days = $duration['days'] ?? $this->daysFromLabel($duration['label'] ?? null) ?? count($record['itinerary'] ?? []);
            $nights = $duration['nights'] ?? $this->nightsFromLabel($duration['label'] ?? null);
            $destinations = $this->destinations($record);
            $country = $record['country'] ?? 'Multiple countries';
            $region = self::CRUISE_REGIONS[$record['id']] ?? $this->regionFor($country);
            $brand = $record['brand'] ?? null;
            $summary = $record['tagline'] ?? $record['description'] ?? null;

            if (! $summary) {
                $summary = isset($record['destinations'])
                    ? 'A guided journey through '.implode(', ', array_slice($record['destinations'], 0, 4)).'.'
                    : 'A '.$duration['label'].' itinerary with '.$brand.'.';
            }

            $departurePort = $record['departure_city']
                ?? $record['embark_port']
                ?? ($record['itinerary'][0]['port'] ?? null);
            $destinationPort = $record['arrival_airport']
                ?? $record['disembark_port']
                ?? ($record['itinerary'][array_key_last($record['itinerary'])]['port'] ?? null);
            $details = $this->details($record, $destinations);
            $sourceData = $record;

            if (isset($record['pricing'])) {
                $sourceData['pricing']['website_price_from_myr'] = $priceFrom;
                $sourceData['pricing']['website_price_to_myr'] = $priceTo;
                $sourceData['pricing']['website_departure_dates'] = $departures;
            }

            $package = TravelPackage::create([
                'source_id' => $record['id'],
                'slug' => $record['slug'] ?? Str::slug($record['title']['en']),
                'name' => $record['title']['en'],
                'country' => $country,
                'region' => $region,
                'type' => self::TYPES[$record['category']] ?? Str::headline($record['category']),
                'ship' => is_array($record['ship'] ?? null) ? ($record['ship']['name'] ?? null) : ($record['ship'] ?? null),
                'brand' => $brand,
                'operator' => $record['operator'] ?? $record['seller'] ?? null,
                'days' => max(1, (int) $days),
                'nights' => $nights,
                'description' => Str::limit($summary, 490),
                'details' => Str::limit($details, 4900),
                'price_from' => $priceFrom,
                'price_to' => $priceTo,
                'rating' => 0,
                'reviews' => 0,
                'departure_port' => $departurePort,
                'destination_port' => $destinationPort,
                'departures' => $departures,
                'destinations' => $destinations,
                'itinerary' => $record['itinerary'] ?? [],
                'source_data' => $sourceData,
                'image' => self::IMAGES[$index % count(self::IMAGES)],
                'published' => true,
            ]);
        }
    }

    private function daysFromLabel(?string $label): ?int
    {
        return $label && preg_match('/(\d+)\s*(?:d|days?)/i', $label, $matches)
            ? (int) $matches[1]
            : null;
    }

    private function nightsFromLabel(?string $label): ?int
    {
        return $label && preg_match('/(\d+)\s*(?:n|nights?)/i', $label, $matches)
            ? (int) $matches[1]
            : null;
    }

    private function destinations(array $record): array
    {
        if (! empty($record['destinations'])) {
            return array_values(array_unique($record['destinations']));
        }

        $places = [];
        foreach ($record['itinerary'] ?? [] as $day) {
            $route = $day['route'] ?? (isset($day['port']) ? [$day['port']] : []);
            foreach ($route as $place) {
                if ($place && ! in_array($place, ['Departure', 'At sea'], true)) {
                    $places[] = $place;
                }
            }
        }

        return array_values(array_unique($places));
    }

    private function regionFor(string $country): string
    {
        return match (true) {
            in_array($country, ['Japan', 'China'], true) => 'Asia',
            in_array($country, ['Türkiye (Turkey)', 'Switzerland'], true) => 'Europe',
            default => 'Other',
        };
    }

    private function details(array $record, array $destinations): string
    {
        $lines = [];

        if (! empty($record['tagline'])) {
            $lines[] = $record['tagline'];
        } elseif (! empty($record['description'])) {
            $lines[] = $record['description'];
        }

        if ($destinations) {
            $lines[] = 'Destinations: '.implode(' · ', $destinations);
        }

        if (! empty($record['tags'])) {
            $lines[] = 'Travel themes: '.implode(' · ', $record['tags']);
        }

        return implode("\n\n", $lines) ?: 'Explore the day-by-day itinerary and tour information below.';
    }
}
