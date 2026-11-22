<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use UnexpectedValueException;

class FarmLocationGeocoder
{
    /** @return list<array{label: string, latitude: float, longitude: float}>|null Null means the shared provider rate limit is active. */
    public function search(string $details, string $barangay, BongabongGeography $geography): ?array
    {
        $query = trim($details).', '.$barangay.', Bongabong, Oriental Mindoro, Philippines';
        $cacheKey = 'farm-location-search:'.hash('sha256', mb_strtolower($query));

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey, []);
        }

        if (! $this->claimProviderSlot()) {
            return null;
        }

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($query, $geography): array {
            $points = $geography->boundaryForMap();
            $latitudes = array_column($points, 'latitude');
            $longitudes = array_column($points, 'longitude');

            $response = Http::acceptJson()
                ->withHeaders([
                    'Accept-Language' => 'en,fil',
                    'User-Agent' => 'AgriConnectERP/1.0 (+'.rtrim((string) config('app.url'), '/').')',
                ])
                ->timeout(8)
                ->get((string) config('services.nominatim.search_url'), [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'countrycodes' => 'ph',
                    'limit' => 8,
                    'viewbox' => implode(',', [min($longitudes), max($latitudes), max($longitudes), min($latitudes)]),
                    'bounded' => 1,
                ]);
            $response->throw();
            $items = $response->json();

            if (! is_array($items)) {
                throw new UnexpectedValueException('The map search response was not a result list.');
            }

            return collect($items)
                ->filter(fn (mixed $item): bool => is_array($item)
                    && isset($item['lat'], $item['lon'], $item['display_name'])
                    && is_numeric($item['lat'])
                    && is_numeric($item['lon'])
                    && is_string($item['display_name']))
                ->map(function (array $item) use ($geography): ?array {
                    $latitude = (float) $item['lat'];
                    $longitude = (float) $item['lon'];

                    if (! $geography->containsPoint($latitude, $longitude)) {
                        return null;
                    }

                    return [
                        'label' => Str::limit(trim($item['display_name']), 220, ''),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ];
                })
                ->filter()
                ->unique(fn (array $item): string => $item['latitude'].','.$item['longitude'])
                ->take(5)
                ->values()
                ->all();
        });
    }

    private function claimProviderSlot(): bool
    {
        return (bool) Cache::lock('farm-location-search:nominatim-slot-lock', 5)->get(function (): bool {
            $now = microtime(true);
            $lastRequestAt = Cache::get('farm-location-search:nominatim-last-request-at');

            if (is_numeric($lastRequestAt) && ($now - (float) $lastRequestAt) < 1.0) {
                return false;
            }

            Cache::put('farm-location-search:nominatim-last-request-at', $now, now()->addMinute());

            return true;
        });
    }
}
