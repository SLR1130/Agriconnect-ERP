<?php

namespace App\Http\Controllers;

use App\Services\BongabongGeography;
use App\Services\FarmLocationGeocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

class FarmLocationSearchController extends Controller
{
    public function __invoke(Request $request, FarmLocationGeocoder $geocoder, BongabongGeography $geography): JsonResponse
    {
        $data = $request->validate([
            'location_details' => ['required', 'string', 'min:2', 'max:255'],
            'barangay' => ['required', 'string', Rule::in($geography->barangays())],
        ]);

        try {
            $results = $geocoder->search($data['location_details'], $data['barangay'], $geography);
        } catch (ConnectionException|RequestException|UnexpectedValueException) {
            return response()->json([
                'message' => 'Map search is unavailable right now. Place the pin directly, use GPS, or enter coordinates instead.',
            ], 502);
        }

        if ($results === null) {
            return response()->json([
                'message' => 'Please wait a moment before searching the map again.',
            ], 429)->header('Retry-After', '1');
        }

        return response()->json(['results' => $results]);
    }
}
