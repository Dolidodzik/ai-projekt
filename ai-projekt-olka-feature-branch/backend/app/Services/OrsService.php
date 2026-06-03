<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OrsService
{
    public function walkingRoute(float $fromLat, float $fromLon, float $toLat, float $toLon): ?array
    {
        $apiKey = (string) config('ors.api_key');
        if ($apiKey === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('ors.base_url'), '/');

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get($baseUrl.'/v2/directions/foot-walking', [
                'start' => $fromLon.','.$fromLat,
                'end' => $toLon.','.$toLat,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $feature = $data['features'][0] ?? null;
        if (! is_array($feature)) {
            return null;
        }

        $summary = $feature['properties']['summary'] ?? [];
        $geometry = $feature['geometry'] ?? null;

        return [
            'distance_m' => (float) ($summary['distance'] ?? 0),
            'duration_s' => (float) ($summary['duration'] ?? 0),
            'geometry' => $geometry,
        ];
    }
}
