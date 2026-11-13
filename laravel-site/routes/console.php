<?php

use App\Models\Booking;
use App\Models\SearchTermCount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\TravellerDocumentStorage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('traveller-documents:cleanup', function (TravellerDocumentStorage $storage) {
    $pendingDeleted = $storage->cleanupPendingBefore(now()->subDays(30));
    $bookedDeleted = 0;

    Booking::query()
        ->whereDate('departure_date', '<=', today()->subDays(90))
        ->chunkById(100, function ($bookings) use ($storage, &$bookedDeleted): void {
            foreach ($bookings as $booking) {
                $details = $booking->guest_details;
                $travellers = $details['travellers'] ?? [];
                $changed = false;

                foreach ($travellers as &$traveller) {
                    $path = $traveller['document_path'] ?? null;
                    if (! is_string($path)) {
                        continue;
                    }

                    if (! $storage->delete($path)) {
                        throw new RuntimeException('Unable to delete an expired traveller document.');
                    }
                    unset($traveller['document_path']);
                    $bookedDeleted++;
                    $changed = true;
                }
                unset($traveller);

                if ($changed) {
                    $details['travellers'] = $travellers;
                    $booking->guest_details = $details;
                    $booking->saveQuietly();
                }
            }
        });

    $this->info("Deleted {$pendingDeleted} expired staged and {$bookedDeleted} expired booking documents.");
})->purpose('Delete staged traveller documents after 30 days and booking documents 90 days after departure');

Schedule::command('traveller-documents:cleanup')->daily();

Artisan::command('search-terms:prune {--days=190 : Keep this many days of search history}', function () {
    $deleted = SearchTermCount::query()
        ->where('searched_on', '<', now()->subDays(max(1, (int) $this->option('days')))->toDateString())
        ->delete();

    $this->info("Deleted {$deleted} old search-term rows.");
})->purpose('Delete search-term counts older than 190 days (trending searches look back six months)');

Schedule::command('search-terms:prune')->daily();
