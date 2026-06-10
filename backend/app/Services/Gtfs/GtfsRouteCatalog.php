<?php

namespace App\Services\Gtfs;

use Illuminate\Support\Facades\DB;

final class GtfsRouteCatalog
{
    public function listRoutes(): array
    {
        $tripCounts = DB::table('gtfs_trips')
            ->select('route_id', DB::raw('COUNT(*) as trip_count'))
            ->groupBy('route_id');

        $rows = DB::table('gtfs_routes as r')
            ->leftJoinSub($tripCounts, 'tc', function ($join): void {
                $join->on('tc.route_id', '=', 'r.id');
            })
            ->select(['r.id', 'r.route_id', 'r.route_short_name', 'r.route_long_name', 'r.route_type'])
            ->selectRaw('COALESCE(tc.trip_count, 0) as trip_count')
            ->orderBy('r.route_short_name')
            ->orderBy('r.route_type')
            ->orderByDesc(DB::raw('COALESCE(tc.trip_count, 0)'))
            ->orderBy('r.id')
            ->get();

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $sn = trim((string) $row->route_short_name);
            $type = (int) $row->route_type;
            $key = $sn !== '' ? $sn.'|'.$type : 'route_id:'.(string) $row->route_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'id' => (int) $row->id,
                'route_id' => (string) $row->route_id,
                'short_name' => (string) $row->route_short_name,
                'long_name' => $row->route_long_name !== null ? (string) $row->route_long_name : null,
                'route_type' => (int) $row->route_type,
            ];
        }

        return $out;
    }

    public function listStops(int $limit = 500, ?string $query = null): array
    {
        $limit = max(1, min($limit, 2000));
        $needle = $query !== null ? trim($query) : '';
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);

        return DB::table('gtfs_stops')
            ->select('id', 'stop_id', 'stop_name', 'stop_lat', 'stop_lon')
            ->when(
                $needle !== '',
                static fn ($q) => $q->where('stop_name', 'ilike', '%'.$escaped.'%')
            )
            ->orderBy('stop_name')
            ->limit($limit)
            ->get()
            ->map(static function ($row): array {
                return [
                    'id' => (int) $row->id,
                    'stop_id' => (string) $row->stop_id,
                    'stop_name' => (string) $row->stop_name,
                    'stop_lat' => (float) $row->stop_lat,
                    'stop_lon' => (float) $row->stop_lon,
                ];
            })
            ->all();
    }
}
