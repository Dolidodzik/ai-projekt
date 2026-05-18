<?php

namespace App\Http\Controllers;

use App\Models\RideHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 50);

        $rides = RideHistory::query()
            ->with(['trip', 'fromStop', 'toStop'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $rides->getCollection()->map(fn (RideHistory $ride) => $this->ridePayload($ride)),
            'meta' => [
                'current_page' => $rides->currentPage(),
                'last_page' => $rides->lastPage(),
                'per_page' => $rides->perPage(),
                'total' => $rides->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:gtfs_trips,id'],
            'from_stop_id' => ['required', 'integer', 'exists:gtfs_stops,id'],
            'to_stop_id' => ['required', 'integer', 'exists:gtfs_stops,id', 'different:from_stop_id'],
        ]);

        $ride = RideHistory::create([
            'user_id' => $request->user()->id,
            'trip_id' => $data['trip_id'],
            'from_stop_id' => $data['from_stop_id'],
            'to_stop_id' => $data['to_stop_id'],
            'created_at' => now(),
        ]);

        $ride->load(['trip', 'fromStop', 'toStop']);

        return response()->json([
            'message' => 'Przejazd dodany do historii.',
            'ride' => $this->ridePayload($ride),
        ], 201);
    }

    private function ridePayload(RideHistory $ride): array
    {
        return [
            'id' => $ride->id,
            'trip_id' => $ride->trip_id,
            'trip_code' => $ride->trip?->trip_id,
            'from_stop_id' => $ride->from_stop_id,
            'from_stop_name' => $ride->fromStop?->stop_name,
            'to_stop_id' => $ride->to_stop_id,
            'to_stop_name' => $ride->toStop?->stop_name,
            'created_at' => $ride->created_at,
        ];
    }
}
