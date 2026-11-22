<?php

namespace Tests\Feature;

use App\Services\BongabongGeography;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FarmLocationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_registration_exposes_an_explicit_privacy_disclosed_farm_map_search(): void
    {
        $this->get(route('farmer.register'))
            ->assertOk()
            ->assertSee('data-farm-location-search', false)
            ->assertSee('data-farm-location-details', false)
            ->assertSee('data-farm-location-barangay', false)
            ->assertSee('Find this place on the map')
            ->assertSee('Search runs only when you press this button.')
            ->assertSee('OpenStreetMap Nominatim')
            ->assertSee('data-farm-location-search-results', false)
            ->assertSee('farm-location/search');
    }

    public function test_search_returns_only_matches_inside_bongabong_and_sends_only_farm_place_details(): void
    {
        $geography = app(BongabongGeography::class);
        $this->assertTrue($geography->containsPoint(12.74642, 121.48741));

        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                ['lat' => '12.74642', 'lon' => '121.48741', 'display_name' => 'Riverside Bridge, Poblacion, Bongabong'],
                ['lat' => '0.0', 'lon' => '0.0', 'display_name' => 'Outside Bongabong'],
            ], 200),
        ]);

        $this->postJson(route('farm-location.search'), [
            'location_details' => 'Riverside Bridge',
            'barangay' => 'Poblacion',
        ])->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.label', 'Riverside Bridge, Poblacion, Bongabong')
            ->assertJsonPath('results.0.latitude', 12.74642)
            ->assertJsonPath('results.0.longitude', 121.48741);

        Http::assertSent(function ($request): bool {
            $query = (string) $request['q'];

            return str_contains($request->url(), 'nominatim.openstreetmap.org/search')
                && str_starts_with($query, 'Riverside Bridge, Poblacion, Bongabong')
                && ! str_contains($query, 'farmer@example.test')
                && ! str_contains($query, 'Farmer Name')
                && $request->hasHeader('User-Agent', 'AgriConnectERP/1.0 (+'.rtrim((string) config('app.url'), '/').')')
                && $request['format'] === 'jsonv2'
                && $request['countrycodes'] === 'ph'
                && (string) $request['bounded'] === '1';
        });
    }

    public function test_invalid_barangay_is_rejected_without_calling_the_geocoder(): void
    {
        Http::fake();

        $this->postJson(route('farm-location.search'), [
            'location_details' => 'Town plaza',
            'barangay' => 'Not a Bongabong barangay',
        ])->assertUnprocessable()->assertJsonValidationErrors('barangay');

        Http::assertNothingSent();
    }

    public function test_public_geocoder_calls_are_limited_and_repeated_searches_use_the_cache(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                ['lat' => '12.74642', 'lon' => '121.48741', 'display_name' => 'Town Plaza, Bongabong'],
            ], 200),
        ]);

        $search = [
            'location_details' => 'Town Plaza',
            'barangay' => 'Poblacion',
        ];

        $this->postJson(route('farm-location.search'), $search)->assertOk();
        $this->postJson(route('farm-location.search'), $search)->assertOk();
        Http::assertSentCount(1);

        $this->postJson(route('farm-location.search'), [
            'location_details' => 'Different public landmark',
            'barangay' => 'Poblacion',
        ])->assertTooManyRequests()
            ->assertJsonPath('message', 'Please wait a moment before searching the map again.');

        Http::assertSentCount(1);
    }

    public function test_provider_failure_returns_a_safe_fallback_message(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([], 503),
        ]);

        $this->postJson(route('farm-location.search'), [
            'location_details' => 'Town Plaza',
            'barangay' => 'Poblacion',
        ])->assertStatus(502)
            ->assertJsonPath('message', 'Map search is unavailable right now. Place the pin directly, use GPS, or enter coordinates instead.');
    }
}
