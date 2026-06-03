<?php

namespace App\Services\Gtfs;

use Illuminate\Support\Facades\DB;

final class GtfsRouteRelations
{
    public function siblingRouteIds(int $routePk): array
    {
        $route = DB::table('gtfs_routes')->where('id', $routePk)->first(['route_short_name', 'route_type']);
        if ($route === null) {
            return [$routePk];
        }
        $norm = trim((string) $route->route_short_name);
        if ($norm === '') {
            return [$routePk];
        }

        return DB::table('gtfs_routes')
            ->whereRaw('TRIM(route_short_name) = ?', [$norm])
            ->where('route_type', (int) $route->route_type)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function tripFirstLastStopIds(int $tripPk): ?array
    {
        $first = DB::table('gtfs_stop_times')->where('trip_id', $tripPk)->orderBy('stop_sequence')->value('stop_id');
        $last = DB::table('gtfs_stop_times')->where('trip_id', $tripPk)->orderByDesc('stop_sequence')->value('stop_id');
        if ($first === null || $last === null) {
            return null;
        }

        return ['first' => (int) $first, 'last' => (int) $last];
    }

    public function tripIdsWithEndpointsOnRoute(array $routeIds, int $firstStopId, int $lastStopId): array
    {
        if ($routeIds === []) {
            return [];
        }

        return DB::table('gtfs_trips as t')
            ->whereIn('t.route_id', $routeIds)
            ->whereRaw(
                '(SELECT stop_id FROM gtfs_stop_times WHERE trip_id = t.id ORDER BY stop_sequence ASC LIMIT 1) = ?',
                [$firstStopId]
            )
            ->whereRaw(
                '(SELECT stop_id FROM gtfs_stop_times WHERE trip_id = t.id ORDER BY stop_sequence DESC LIMIT 1) = ?',
                [$lastStopId]
            )
            ->pluck('t.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
