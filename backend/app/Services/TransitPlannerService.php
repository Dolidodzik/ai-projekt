<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TransitPlannerService
{
    public function nearestStop(float $lat, float $lon): ?array
    {
        $row = DB::table('gtfs_stops')
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
            ->orderBy('distance_m')
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
            'distance_m' => (float) $row->distance_m,
        ];
    }

    public function directTrip(int $fromStopId, int $toStopId): ?array
    {
        $row = DB::table('gtfs_stop_times as from_st')
            ->join('gtfs_stop_times as to_st', 'to_st.trip_id', '=', 'from_st.trip_id')
            ->join('gtfs_trips as t', 't.id', '=', 'from_st.trip_id')
            ->join('gtfs_routes as r', 'r.id', '=', 't.route_id')
            ->select([
                't.id as trip_pk',
                't.trip_id',
                'r.id as route_pk',
                'r.route_id',
                'r.route_short_name',
                'r.route_long_name',
                'from_st.departure_time as from_departure_time',
                'to_st.arrival_time as to_arrival_time',
                'from_st.stop_sequence as from_sequence',
                'to_st.stop_sequence as to_sequence',
            ])
            ->where('from_st.stop_id', $fromStopId)
            ->where('to_st.stop_id', $toStopId)
            ->whereColumn('from_st.stop_sequence', '<', 'to_st.stop_sequence')
            ->orderByRaw('(to_st.stop_sequence - from_st.stop_sequence) asc')
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'type' => 'direct',
            'trip_id' => (string) $row->trip_id,
            'trip_pk' => (int) $row->trip_pk,
            'route' => [
                'id' => (int) $row->route_pk,
                'route_id' => (string) $row->route_id,
                'short_name' => (string) $row->route_short_name,
                'long_name' => $row->route_long_name !== null ? (string) $row->route_long_name : null,
            ],
            'from_departure_time' => (string) $row->from_departure_time,
            'to_arrival_time' => (string) $row->to_arrival_time,
            'stops_span' => (int) $row->to_sequence - (int) $row->from_sequence,
        ];
    }

    public function oneTransferTrip(int $fromStopId, int $toStopId): ?array
    {
        $sql = <<<'SQL'
WITH leg1 AS (
    SELECT
        a.trip_id as trip1_id,
        b.stop_id as transfer_stop_id,
        a.departure_time as from_departure_time,
        b.arrival_time as transfer_arrival_time,
        a.stop_sequence as from_seq,
        b.stop_sequence as transfer_seq
    FROM gtfs_stop_times a
    JOIN gtfs_stop_times b ON b.trip_id = a.trip_id
    WHERE a.stop_id = ? AND a.stop_sequence < b.stop_sequence
),
leg2 AS (
    SELECT
        c.trip_id as trip2_id,
        c.stop_id as transfer_stop_id,
        d.arrival_time as to_arrival_time,
        c.departure_time as transfer_departure_time,
        c.stop_sequence as transfer_seq,
        d.stop_sequence as to_seq
    FROM gtfs_stop_times c
    JOIN gtfs_stop_times d ON d.trip_id = c.trip_id
    WHERE d.stop_id = ? AND c.stop_sequence < d.stop_sequence
)
SELECT
    l1.trip1_id,
    l2.trip2_id,
    l1.transfer_stop_id,
    l1.from_departure_time,
    l1.transfer_arrival_time,
    l2.transfer_departure_time,
    l2.to_arrival_time,
    (l1.transfer_seq - l1.from_seq) as span1,
    (l2.to_seq - l2.transfer_seq) as span2
FROM leg1 l1
JOIN leg2 l2 ON l1.transfer_stop_id = l2.transfer_stop_id
WHERE l1.trip1_id <> l2.trip2_id
ORDER BY (l1.transfer_seq - l1.from_seq) + (l2.to_seq - l2.transfer_seq) ASC
LIMIT 1
SQL;

        $row = DB::selectOne($sql, [$fromStopId, $toStopId]);
        if (! $row) {
            return null;
        }

        $trip1 = DB::table('gtfs_trips as t')
            ->join('gtfs_routes as r', 'r.id', '=', 't.route_id')
            ->select('t.trip_id', 'r.id as route_pk', 'r.route_id', 'r.route_short_name', 'r.route_long_name')
            ->where('t.id', (int) $row->trip1_id)
            ->first();

        $trip2 = DB::table('gtfs_trips as t')
            ->join('gtfs_routes as r', 'r.id', '=', 't.route_id')
            ->select('t.trip_id', 'r.id as route_pk', 'r.route_id', 'r.route_short_name', 'r.route_long_name')
            ->where('t.id', (int) $row->trip2_id)
            ->first();

        if (! $trip1 || ! $trip2) {
            return null;
        }

        return [
            'type' => 'one_transfer',
            'transfer_stop_id' => (int) $row->transfer_stop_id,
            'legs' => [
                [
                    'trip_id' => (string) $trip1->trip_id,
                    'route' => [
                        'id' => (int) $trip1->route_pk,
                        'route_id' => (string) $trip1->route_id,
                        'short_name' => (string) $trip1->route_short_name,
                        'long_name' => $trip1->route_long_name !== null ? (string) $trip1->route_long_name : null,
                    ],
                    'from_departure_time' => (string) $row->from_departure_time,
                    'to_arrival_time' => (string) $row->transfer_arrival_time,
                    'stops_span' => (int) $row->span1,
                ],
                [
                    'trip_id' => (string) $trip2->trip_id,
                    'route' => [
                        'id' => (int) $trip2->route_pk,
                        'route_id' => (string) $trip2->route_id,
                        'short_name' => (string) $trip2->route_short_name,
                        'long_name' => $trip2->route_long_name !== null ? (string) $trip2->route_long_name : null,
                    ],
                    'from_departure_time' => (string) $row->transfer_departure_time,
                    'to_arrival_time' => (string) $row->to_arrival_time,
                    'stops_span' => (int) $row->span2,
                ],
            ],
        ];
    }
}
