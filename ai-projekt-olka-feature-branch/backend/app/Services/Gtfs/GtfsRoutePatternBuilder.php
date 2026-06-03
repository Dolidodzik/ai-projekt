<?php

namespace App\Services\Gtfs;

use Illuminate\Support\Facades\DB;

final class GtfsRoutePatternBuilder
{
    public function __construct(
        private readonly GtfsRouteRelations $routeRelations,
        private readonly GtfsTripDetailsPresenter $tripDetails,
    ) {
    }

    public function routePattern(int $routePk): ?array
    {
        $route = DB::table('gtfs_routes')->where('id', $routePk)->first();
        if (! $route) {
            return null;
        }

        $siblingIds = $this->routeRelations->siblingRouteIds($routePk);

        $groups = $this->representativeTripsByDirectionKey($siblingIds);

        $fromGtfs = [];
        foreach ($groups as $group) {
            $tripPk = (int) $group->trip_pk;
            $directionKey = (int) $group->direction_key;
            $fromGtfs[] = $this->tripDetails->directionPatternFromTrip(
                $tripPk,
                $directionKey,
                $directionKey === -1 ? null : $directionKey
            );
        }

        if (count($fromGtfs) >= 2) {
            $directions = $fromGtfs;
            $endpointSplit = false;
        } else {
            $fromEndpoints = $this->directionsFromReversingEndpoints($siblingIds);
            if (count($fromEndpoints) === 2) {
                $directions = $fromEndpoints;
                $endpointSplit = true;
            } else {
                $directions = $fromGtfs;
                $endpointSplit = false;
            }
        }

        $directions = array_values(array_filter($directions, static fn (array $d): bool => count($d['stops']) >= 2));
        if (count($directions) < 2) {
            $endpointSplit = false;
        } else {
            $directions = $this->limitRoutePatternDirectionsToTwo($directions, $siblingIds);
            if (count($directions) < 2) {
                $endpointSplit = false;
            }
        }

        return [
            'route' => [
                'id' => (int) $route->id,
                'route_id' => (string) $route->route_id,
                'short_name' => (string) $route->route_short_name,
                'long_name' => $route->route_long_name !== null ? (string) $route->route_long_name : null,
                'route_type' => (int) $route->route_type,
            ],
            'directions' => $directions,
            'endpoint_split' => $endpointSplit,
        ];
    }

    private function representativeTripsByDirectionKey(array $routeIds): array
    {
        if ($routeIds === []) {
            return [];
        }

        $stopCounts = DB::table('gtfs_stop_times')
            ->select('trip_id')
            ->selectRaw('COUNT(*) as stop_n')
            ->groupBy('trip_id');

        $rows = DB::table('gtfs_trips as t')
            ->joinSub($stopCounts, 'sc', 'sc.trip_id', '=', 't.id')
            ->whereIn('t.route_id', $routeIds)
            ->selectRaw('COALESCE(t.direction_id, -1) as direction_key')
            ->selectRaw('t.id as trip_pk')
            ->selectRaw('sc.stop_n')
            ->orderBy('direction_key')
            ->orderByDesc('sc.stop_n')
            ->orderBy('t.id')
            ->get();

        $byKey = [];
        foreach ($rows as $row) {
            $k = (int) $row->direction_key;
            if (isset($byKey[$k])) {
                continue;
            }
            $byKey[$k] = (object) [
                'direction_key' => $k,
                'trip_pk' => (int) $row->trip_pk,
            ];
        }
        ksort($byKey);

        return array_values($byKey);
    }

    private function tripCountForRouteDirectionBuckets(array $routeIds, int $directionKey): int
    {
        if ($routeIds === []) {
            return 0;
        }
        $q = DB::table('gtfs_trips')->whereIn('route_id', $routeIds);
        if ($directionKey === -1) {
            $q->whereNull('direction_id');
        } else {
            $q->where('direction_id', $directionKey);
        }

        return (int) $q->count();
    }

    private function limitRoutePatternDirectionsToTwo(array $directions, array $routeIds): array
    {
        if (count($directions) <= 2 || $routeIds === []) {
            return $directions;
        }
        $scored = [];
        foreach ($directions as $d) {
            $k = (int) $d['direction_key'];
            $scored[] = [
                'trips' => $this->tripCountForRouteDirectionBuckets($routeIds, $k),
                'stops' => count($d['stops']),
                'd' => $d,
            ];
        }
        usort($scored, static function (array $a, array $b): int {
            if ($a['trips'] !== $b['trips']) {
                return $b['trips'] <=> $a['trips'];
            }

            return $b['stops'] <=> $a['stops'];
        });
        $scored = array_slice($scored, 0, 2);
        usort($scored, static fn (array $a, array $b): int => (int) ($a['d']['direction_key'] ?? 0) <=> (int) ($b['d']['direction_key'] ?? 0));

        return array_map(static fn (array $row): array => $row['d'], $scored);
    }

    private function directionsFromReversingEndpoints(array $routeIds): array
    {
        if ($routeIds === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($routeIds), '?'));
        $rows = DB::select(
            'SELECT first_stop_id, last_stop_id, MIN(trip_pk) AS trip_pk, COUNT(*)::int AS trip_n
            FROM (
                SELECT t.id AS trip_pk,
                    (SELECT stop_id FROM gtfs_stop_times WHERE trip_id = t.id ORDER BY stop_sequence ASC LIMIT 1) AS first_stop_id,
                    (SELECT stop_id FROM gtfs_stop_times WHERE trip_id = t.id ORDER BY stop_sequence DESC LIMIT 1) AS last_stop_id
                FROM gtfs_trips t
                WHERE t.route_id IN ('.$ph.')
            ) s
            WHERE first_stop_id IS NOT NULL AND last_stop_id IS NOT NULL
            GROUP BY first_stop_id, last_stop_id',
            $routeIds
        );

        $byMeta = [];
        foreach ($rows as $row) {
            $f = (int) $row->first_stop_id;
            $l = (int) $row->last_stop_id;
            if ($f === $l) {
                continue;
            }
            $byMeta[$f.','.$l] = [
                'trip_pk' => (int) $row->trip_pk,
                'cnt' => (int) $row->trip_n,
            ];
        }

        $candidates = [];
        foreach ($byMeta as $key => $meta) {
            $parts = explode(',', $key);
            $f = (int) $parts[0];
            $l = (int) $parts[1];
            $revKey = $l.','.$f;
            if (! isset($byMeta[$revKey])) {
                continue;
            }
            if ($key >= $revKey) {
                continue;
            }
            $candidates[] = [
                'fwd' => $meta['trip_pk'],
                'rev' => $byMeta[$revKey]['trip_pk'],
                'score' => $meta['cnt'] + $byMeta[$revKey]['cnt'],
            ];
        }

        if ($candidates === []) {
            return [];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $best = $candidates[0];

        return [
            $this->tripDetails->directionPatternFromTrip($best['fwd'], 0, null),
            $this->tripDetails->directionPatternFromTrip($best['rev'], 1, null),
        ];
    }
}
