<?php

namespace App\Http\Controllers;

use App\Services\OrsService;
use App\Services\TransitPlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            return response()->json(['message' => 'No GTFS stops found.'], 404);
        }

        return response()->json($stop);
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
        ]);

        $maxTransfers = (int) ($data['max_transfers'] ?? 1);

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

        $direct = $this->planner->directTrip($from['stop']['id'], $to['stop']['id']);
        $transit = $direct;

        if (! $transit && $maxTransfers >= 1) {
            $transit = $this->planner->oneTransferTrip($from['stop']['id'], $to['stop']['id']);
        }

        if (! $transit) {
            return response()->json([
                'message' => 'No route found for selected endpoints.',
                'from_stop' => $from['stop'],
                'to_stop' => $to['stop'],
                'max_transfers' => $maxTransfers,
            ], 404);
        }

        return response()->json([
            'from_stop' => $from['stop'],
            'to_stop' => $to['stop'],
            'max_transfers' => $maxTransfers,
            'walking_segments' => array_values(array_filter([
                $from['walk'] ?? null,
                $to['walk'] ?? null,
            ])),
            'transit' => $transit,
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
                return response()->json(['message' => "Unknown {$prefix}_stop_id."], 404);
            }

            return ['stop' => $stop, 'walk' => null];
        }

        if ($lat === null || $lon === null) {
            return response()->json([
                'message' => "Provide either {$prefix}_stop_id or {$prefix}_lat + {$prefix}_lon.",
            ], 422);
        }

        $nearest = $this->planner->nearestStop((float) $lat, (float) $lon);
        if (! $nearest) {
            return response()->json(['message' => 'No GTFS stops found.'], 404);
        }

        $walk = $this->ors->walkingRoute(
            (float) $lat,
            (float) $lon,
            (float) $nearest['stop_lat'],
            (float) $nearest['stop_lon']
        );

        return [
            'stop' => $nearest,
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
