<?php

namespace App\Services\Gtfs;

use Illuminate\Support\Facades\DB;

final class GtfsTripDetailsPresenter
{
    public function tripDetails(int $tripId): ?array
    {
        $trip = DB::table('gtfs_trips as t')
            ->join('gtfs_routes as r', 'r.id', '=', 't.route_id')
            ->select([
                't.id as trip_pk',
                't.trip_id',
                't.shape_id',
                't.direction_id',
                'r.id as route_pk',
                'r.route_id',
                'r.route_short_name',
                'r.route_long_name',
            ])
            ->where('t.id', $tripId)
            ->first();

        if (! $trip) {
            return null;
        }

        $stops = $this->publicStopsForDisplay($tripId);

        $shape = [];
        if ($trip->shape_id !== null) {
            $shape = $this->shapePointsFromGtfs((string) $trip->shape_id);
        }

        return [
            'trip' => [
                'id' => (int) $trip->trip_pk,
                'trip_id' => (string) $trip->trip_id,
                'shape_id' => $trip->shape_id !== null ? (string) $trip->shape_id : null,
                'direction_id' => $trip->direction_id !== null ? (int) $trip->direction_id : null,
                'route' => [
                    'id' => (int) $trip->route_pk,
                    'route_id' => (string) $trip->route_id,
                    'short_name' => (string) $trip->route_short_name,
                    'long_name' => $trip->route_long_name !== null ? (string) $trip->route_long_name : null,
                ],
            ],
            'stops' => $stops,
            'shape' => $shape,
        ];
    }

    public function directionPatternFromTrip(int $tripPk, int $directionKey, ?int $directionId): array
    {
        $stops = $this->publicStopsForDisplay($tripPk);
        $last = $stops[array_key_last($stops)] ?? null;

        return [
            'direction_key' => $directionKey,
            'direction_id' => $directionId,
            'representative_trip_id' => $tripPk,
            'headsign' => $last !== null ? (string) $last['stop']['stop_name'] : null,
            'stops' => $stops,
            'shape' => $this->mapPolylineForTrip($tripPk, $stops),
        ];
    }

    private function isPublicDepotStop(string $stopName): bool
    {
        $lower = mb_strtolower($stopName, 'UTF-8');

        return preg_match('/zajezdn/i', $stopName) === 1 && str_contains($lower, 'mpk');
    }

    private function renumberPublicStopSequence(array $stops): array
    {
        $out = [];
        $seq = 1;
        foreach ($stops as $row) {
            $row['stop_sequence'] = $seq++;
            $out[] = $row;
        }

        return $out;
    }

    private function trimDepotStopsFromSequence(array $stops): array
    {
        while ($stops !== [] && $this->isPublicDepotStop($stops[0]['stop']['stop_name'])) {
            array_shift($stops);
        }
        while ($stops !== [] && $this->isPublicDepotStop($stops[array_key_last($stops)]['stop']['stop_name'])) {
            array_pop($stops);
        }

        return $this->renumberPublicStopSequence($stops);
    }

    private function publicStopsForDisplay(int $tripPk): array
    {
        $raw = $this->stopSequenceForTrip($tripPk);
        $trimmed = $this->trimDepotStopsFromSequence($raw);

        return $trimmed !== [] ? $trimmed : $this->renumberPublicStopSequence($raw);
    }

    private function stopSequenceForTrip(int $tripId): array
    {
        return DB::table('gtfs_stop_times as st')
            ->join('gtfs_stops as s', 's.id', '=', 'st.stop_id')
            ->select([
                'st.stop_sequence',
                'st.arrival_time',
                'st.departure_time',
                's.id as stop_pk',
                's.stop_id',
                's.stop_name',
                's.stop_lat',
                's.stop_lon',
            ])
            ->where('st.trip_id', $tripId)
            ->orderBy('st.stop_sequence')
            ->get()
            ->map(fn (object $row): array => [
                'stop_sequence' => (int) $row->stop_sequence,
                'arrival_time' => (string) $row->arrival_time,
                'departure_time' => (string) $row->departure_time,
                'stop' => [
                    'id' => (int) $row->stop_pk,
                    'stop_id' => (string) $row->stop_id,
                    'stop_name' => (string) $row->stop_name,
                    'stop_lat' => (float) $row->stop_lat,
                    'stop_lon' => (float) $row->stop_lon,
                ],
            ])
            ->all();
    }

    private function shapePointsFromGtfs(string $shapeId): array
    {
        return DB::table('gtfs_shapes')
            ->select('shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence')
            ->where('shape_id', $shapeId)
            ->orderBy('shape_pt_sequence')
            ->get()
            ->map(static function ($row): array {
                return [
                    'lat' => (float) $row->shape_pt_lat,
                    'lon' => (float) $row->shape_pt_lon,
                    'sequence' => (int) $row->shape_pt_sequence,
                ];
            })
            ->all();
    }

    private function mapPolylineForTrip(int $tripPk, array $stops): array
    {
        $shapeId = DB::table('gtfs_trips')->where('id', $tripPk)->value('shape_id');
        if ($shapeId !== null && (string) $shapeId !== '') {
            $pts = $this->shapePointsFromGtfs((string) $shapeId);
            if (count($pts) > 1) {
                return $pts;
            }
        }

        $out = [];
        $n = 0;
        foreach ($stops as $row) {
            $out[] = [
                'lat' => (float) $row['stop']['stop_lat'],
                'lon' => (float) $row['stop']['stop_lon'],
                'sequence' => ++$n,
            ];
        }

        return $out;
    }
}
