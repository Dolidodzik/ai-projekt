<?php

namespace App\Services;

use App\Services\Gtfs\GtfsCalendar;
use App\Services\Gtfs\GtfsScheduleService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransitPlannerService
{
    private const WALK_RADIUS_METERS = 300;

    private const NEARBY_STOPS_LIMIT = 0;

    public function __construct(
        private readonly GtfsCalendar $gtfsCalendar,
        private readonly GtfsScheduleService $gtfsSchedule,
    ) {
    }

    public function nearestStop(float $lat, float $lon): ?array
    {
        $stops = $this->nearestStops($lat, $lon, 1, PHP_FLOAT_MAX);

        return $stops[0] ?? null;
    }

    public function nearestStops(
        float $lat,
        float $lon,
        int $limit = self::NEARBY_STOPS_LIMIT,
        float $radiusM = self::WALK_RADIUS_METERS
    ): array {
        return DB::table('gtfs_stops')
            ->select([
                'id',
                'stop_id',
                'stop_name',
                'stop_lat',
                'stop_lon',
            ])
            ->selectRaw(
                '6371000 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(stop_lat)) * cos(radians(stop_lon) - radians(?)) + sin(radians(?)) * sin(radians(stop_lat))))) as distance_m',
                [$lat, $lon, $lat]
            )
            ->whereRaw(
                '6371000 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians(stop_lat)) * cos(radians(stop_lon) - radians(?)) + sin(radians(?)) * sin(radians(stop_lat))))) <= ?',
                [$lat, $lon, $lat, $radiusM]
            )
            ->orderBy('distance_m')
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->get()
            ->map(static function (object $row): array {
                return [
                    'id' => (int) $row->id,
                    'stop_id' => (string) $row->stop_id,
                    'stop_name' => (string) $row->stop_name,
                    'stop_lat' => (float) $row->stop_lat,
                    'stop_lon' => (float) $row->stop_lon,
                    'distance_m' => (float) $row->distance_m,
                ];
            })
            ->all();
    }

    public function walkSeconds(
        float $fromLat,
        float $fromLon,
        float $toLat,
        float $toLon,
        float $speedKmh = 5.0
    ): int {
        $distanceM = $this->haversineMeters($fromLat, $fromLon, $toLat, $toLon);

        return (int) round(($distanceM / 1000) / $speedKmh * 3600);
    }

    public function localDepartSeconds(DateTimeInterface $date): int
    {
        return $this->gtfsCalendar->wallClockToServiceSeconds($date);
    }

    public function findTransitOptions(
        int|array $fromStopIds,
        int|array $toStopIds,
        int $maxTransfers,
        ?DateTimeInterface $departAt = null,
        int $limit = 5,
        ?array $accessSecondsByStop = null,
        ?array $egressDistanceByStop = null
    ): array {
        $fromStopIds = array_values(array_unique(array_map('intval', is_array($fromStopIds) ? $fromStopIds : [$fromStopIds])));
        $toStopIds = array_values(array_unique(array_map('intval', is_array($toStopIds) ? $toStopIds : [$toStopIds])));
        if ($fromStopIds === [] || $toStopIds === []) {
            return [];
        }

        if (count($fromStopIds) === 1 && count($toStopIds) === 1 && $fromStopIds[0] === $toStopIds[0]) {
            return [];
        }

        $baseUrl = config('services.route_planner.url');
        if ($baseUrl === null || $baseUrl === '') {
            Log::warning('route_planner_unconfigured', ['hint' => 'Set ROUTE_PLANNER_URL in .env']);

            return [];
        }

        $departAt ??= now();
        $payload = [
            'from_stop_ids' => $fromStopIds,
            'to_stop_ids' => $toStopIds,
            'max_transfers' => $maxTransfers,
            'depart_at' => \Illuminate\Support\Carbon::parse($departAt)->toIso8601String(),
            'limit' => $limit,
        ];
        if ($accessSecondsByStop !== null && $accessSecondsByStop !== []) {
            $payload['access_seconds_by_stop'] = $accessSecondsByStop;
        }
        if ($egressDistanceByStop !== null && $egressDistanceByStop !== []) {
            $payload['egress_distance_by_stop'] = $egressDistanceByStop;
        }

        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->post(rtrim((string) $baseUrl, '/').'/plan-transit', $payload);
        } catch (\Throwable $e) {
            Log::warning('route_planner_unavailable', ['message' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('route_planner_http_error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return [];
        }

        $options = $response->json('options');

        return is_array($options) ? $options : [];
    }

    public function listRoutes(): array
    {
        return $this->gtfsSchedule->listRoutes();
    }

    public function listStops(int $limit = 500, ?string $query = null): array
    {
        return $this->gtfsSchedule->listStops($limit, $query);
    }

    public function routePattern(int $routePk): ?array
    {
        return $this->gtfsSchedule->routePattern($routePk);
    }

    public function routeStopDepartures(
        int $routePk,
        int $stopPk,
        ?int $directionKey,
        DateTimeInterface $date,
        ?int $tripPatternId = null,
        bool $useTripEndpoints = false
    ): array {
        return $this->gtfsSchedule->routeStopDepartures($routePk, $stopPk, $directionKey, $date, $tripPatternId, $useTripEndpoints);
    }

    public function stopBoardDepartures(int $stopPk, DateTimeInterface $date, ?int $routePkFilter = null, int $limit = 400): array
    {
        return $this->gtfsSchedule->stopBoardDepartures($stopPk, $date, $routePkFilter, $limit);
    }

    public function tripDetails(int $tripId): ?array
    {
        return $this->gtfsSchedule->tripDetails($tripId);
    }

    private function haversineMeters(float $fromLat, float $fromLon, float $toLat, float $toLon): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($fromLat);
        $latTo = deg2rad($toLat);
        $latDelta = deg2rad($toLat - $fromLat);
        $lonDelta = deg2rad($toLon - $fromLon);
        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
