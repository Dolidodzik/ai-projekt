<?php

namespace App\Http\Controllers;

use App\Services\OrsService;
use App\Services\TransitPlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RoutePlannerController extends Controller
{
    public function __construct(
        private readonly TransitPlannerService $planner,
        private readonly OrsService $ors
    ) {
    }

    public function nearestStop(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $stop = $this->planner->nearestStop((float) $data['lat'], (float) $data['lon']);
        if (! $stop) {
            return response()->json(['message' => 'Nie znaleziono przystankow GTFS.'], 404);
        }

        return response()->json($stop);
    }

    public function listRoutes(): JsonResponse
    {
        return response()->json([
            'routes' => $this->planner->listRoutes(),
        ]);
    }

    public function tripDetails(int $trip_id): JsonResponse
    {
        $details = $this->planner->tripDetails($trip_id);
        if (! $details) {
            return response()->json(['message' => 'Nie znaleziono kursu.'], 404);
        }

        return response()->json($details);
    }

    public function planRoute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_stop_id' => ['nullable', 'integer', 'exists:gtfs_stops,id'],
            'to_stop_id' => ['nullable', 'integer', 'exists:gtfs_stops,id'],
            'from_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'from_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'to_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'to_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'max_transfers' => ['nullable', 'integer', 'min:0', 'max:3'],
            'depart_at' => ['nullable', 'date'],
        ]);

        $maxTransfers = (int) ($data['max_transfers'] ?? 3);
        $departAt = isset($data['depart_at']) ? Carbon::parse($data['depart_at']) : now();

        $from = $this->resolveEndpoint(
            $data['from_stop_id'] ?? null,
            $data['from_lat'] ?? null,
            $data['from_lon'] ?? null,
            'from'
        );
        if ($from instanceof JsonResponse) {
            return $from;
        }

        $to = $this->resolveEndpoint(
            $data['to_stop_id'] ?? null,
            $data['to_lat'] ?? null,
            $data['to_lon'] ?? null,
            'to'
        );
        if ($to instanceof JsonResponse) {
            return $to;
        }

        $fromStopIds = $from['nearby_stop_ids'] ?? [$from['stop']['id']];
        $toStopIds = $to['nearby_stop_ids'] ?? [$to['stop']['id']];
        $accessSecondsByStop = null;

        if (isset($from['coord'], $from['nearby_stops'])) {
            $baseSeconds = $this->planner->localDepartSeconds($departAt);
            $accessSecondsByStop = [];
            $primaryWalkSeconds = isset($from['walk']['ors']['duration_s'])
                ? (int) $from['walk']['ors']['duration_s']
                : null;

            foreach ($from['nearby_stops'] as $stop) {
                $walkSeconds = $this->planner->walkSeconds(
                    (float) $from['coord']['lat'],
                    (float) $from['coord']['lon'],
                    (float) $stop['stop_lat'],
                    (float) $stop['stop_lon']
                );

                if ($primaryWalkSeconds !== null && $primaryWalkSeconds > 0 && (int) $stop['id'] === (int) $from['stop']['id']) {
                    $walkSeconds = $primaryWalkSeconds;
                }

                $accessSecondsByStop[(int) $stop['id']] = $baseSeconds + $walkSeconds;
            }
        }

        $egressDistanceByStop = null;
        if (isset($to['nearby_stops'])) {
            $egressDistanceByStop = [];
            foreach ($to['nearby_stops'] as $stop) {
                $egressDistanceByStop[(int) $stop['id']] = (int) round((float) $stop['distance_m']);
            }
        }

        $transitOptions = $this->planner->findTransitOptions(
            $fromStopIds,
            $toStopIds,
            $maxTransfers,
            $departAt,
            5,
            $accessSecondsByStop,
            $egressDistanceByStop
        );

        if ($transitOptions === []) {
            return response()->json([
                'message' => 'Nie znaleziono polaczenia dla wybranych punktow.',
                'from_stop' => $from['stop'],
                'to_stop' => $to['stop'],
                'max_transfers' => $maxTransfers,
                'depart_at' => $departAt->toIso8601String(),
            ], 404);
        }

        return response()->json([
            'from_stop' => $from['stop'],
            'to_stop' => $to['stop'],
            'max_transfers' => $maxTransfers,
            'depart_at' => $departAt->toIso8601String(),
            'walking_segments' => array_values(array_filter([
                $from['walk'] ?? null,
                $to['walk'] ?? null,
            ])),
            'transit_options' => $transitOptions,
            'transit' => $transitOptions[0],
        ]);
    }

    private function resolveEndpoint(
        mixed $stopId,
        mixed $lat,
        mixed $lon,
        string $prefix
    ): array|JsonResponse {
        if ($stopId !== null) {
            $stop = $this->stopById((int) $stopId);
            if (! $stop) {
                return response()->json(['message' => "Nieznany identyfikator przystanku ({$prefix}_stop_id)."], 404);
            }

            return ['stop' => $stop, 'walk' => null];
        }

        if ($lat === null || $lon === null) {
            return response()->json([
                'message' => "Podaj {$prefix}_stop_id albo wspolrzedne {$prefix}_lat i {$prefix}_lon.",
            ], 422);
        }

        $nearbyStops = $this->planner->nearestStops((float) $lat, (float) $lon);
        if ($nearbyStops === []) {
            $nearest = $this->planner->nearestStop((float) $lat, (float) $lon);
            if (! $nearest) {
                return response()->json(['message' => 'Nie znaleziono przystankow GTFS.'], 404);
            }

            $nearbyStops = [$nearest];
        }

        $nearest = $nearbyStops[0];
        $walk = $this->ors->walkingRoute(
            (float) $lat,
            (float) $lon,
            (float) $nearest['stop_lat'],
            (float) $nearest['stop_lon']
        );

        return [
            'stop' => $nearest,
            'nearby_stops' => $nearbyStops,
            'nearby_stop_ids' => array_map(static fn (array $stop): int => (int) $stop['id'], $nearbyStops),
            'coord' => ['lat' => (float) $lat, 'lon' => (float) $lon],
            'walk' => [
                'type' => "{$prefix}_location_to_stop",
                'from' => ['lat' => (float) $lat, 'lon' => (float) $lon],
                'to_stop_id' => (int) $nearest['id'],
                'ors' => $walk,
            ],
        ];
    }

    private function stopById(int $stopId): ?array
    {
        $row = \Illuminate\Support\Facades\DB::table('gtfs_stops')
            ->select('id', 'stop_id', 'stop_name', 'stop_lat', 'stop_lon')
            ->where('id', $stopId)
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'stop_id' => (string) $row->stop_id,
            'stop_name' => (string) $row->stop_name,
            'stop_lat' => (float) $row->stop_lat,
            'stop_lon' => (float) $row->stop_lon,
            'distance_m' => 0.0,
        ];
    }
}
