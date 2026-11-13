<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Guide;
use App\Models\Ship;
use App\Models\TravelPackage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /confirmation',
            'Disallow: /payment-failed',
            'Disallow: /account',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot',
            'Disallow: /reset',
            'Disallow: /book/',
            'Disallow: /search?',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function build(): string
    {
        $entries = [];
        foreach (['home', 'search', 'deals', 'destinations', 'ships', 'mice', 'blog', 'about', 'contact', 'faq', 'privacy', 'terms'] as $name) {
            $entries[] = [route($name), null];
        }
        foreach (TravelPackage::query()->where('published', true)->get(['slug', 'updated_at']) as $package) {
            $entries[] = [route('packages.show', ['package' => $package->slug]), $package->updated_at];
        }
        foreach (Country::query()->get(['slug', 'updated_at']) as $country) {
            $entries[] = [route('destinations.country', ['country' => $country->slug]), $country->updated_at];
        }
        foreach (Ship::query()->get(['slug', 'updated_at']) as $ship) {
            $entries[] = [route('ships.show', ['ship' => $ship->slug]), $ship->updated_at];
        }
        foreach (Guide::query()->where('published', true)->get(['slug', 'updated_at']) as $guide) {
            $entries[] = [route('blog.show', ['slug' => $guide->slug]), $guide->updated_at];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($entries as [$location, $modified]) {
            $xml .= '  <url><loc>'.htmlspecialchars($location, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
            if ($modified) {
                $xml .= '<lastmod>'.$modified->toDateString().'</lastmod>';
            }
            $xml .= "</url>\n";
        }

        return $xml.'</urlset>'."\n";
    }
}
