<?php

namespace App\Services\Gtfs;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class GtfsDepartureBoard
{
    public function __construct(
        private readonly GtfsCalendar $calendar,
        private readonly GtfsRouteRelations $routeRelations,
    ) {
    }

    public function routeStopDepartures(
        int $routePk,
        int $stopPk,
        ?int $directionKey,
        DateTimeInterface $date,
        ?int $tripPatternId = null,
        bool $useTripEndpoints = false
    ): array {
        $services = $this->calendar->activeServiceIds($date);
        if ($services === []) {
            return [];
        }

        $siblingIds = $this->routeRelations->siblingRouteIds($routePk);

        $query = DB::table('gtfs_stop_times as st')
            ->join('gtfs_trips as t', 't.id', '=', 'st.trip_id')
            ->whereIn('t.route_id', $siblingIds)
            ->where('st.stop_id', $stopPk)
            ->whereIn('t.service_id', $services);

        if ($useTripEndpoints && $tripPatternId !== null) {
            $ends = $this->routeRelations->tripFirstLastStopIds($tripPatternId);
            if ($ends === null) {
                return [];
            }
            $matchingTripIds = $this->routeRelations->tripIdsWithEndpointsOnRoute($siblingIds, $ends['first'], $ends['last']);
            if ($matchingTripIds === []) {
                return [];
            }
            $query->whereIn('t.id', $matchingTripIds);
        } elseif ($directionKey !== null) {
            if ($directionKey === -1) {
                $query->whereNull('t.direction_id');
            } else {
                $query->where('t.direction_id', $directionKey);
            }
        }

        $times = $query->distinct()->pluck('st.departure_time')->map(static fn ($t): string => (string) $t)->all();
        usort($times, fn (string $a, string $b): int => $this->calendar->gtfsTimeToSeconds($a) <=> $this->calendar->gtfsTimeToSeconds($b));

        return array_values(array_unique($times));
    }

    public function stopBoardDepartures(int $stopPk, DateTimeInterface $date, ?int $routePkFilter = null, int $limit = 400): array
    {
        $services = $this->calendar->activeServiceIds($date);
        if ($services === []) {
            return [];
        }

        $rows = DB::table('gtfs_stop_times as st')
            ->join('gtfs_trips as t', 't.id', '=', 'st.trip_id')
            ->join('gtfs_routes as r', 'r.id', '=', 't.route_id')
            ->where('st.stop_id', $stopPk)
            ->whereIn('t.service_id', $services)
            ->when($routePkFilter !== null, static fn ($q) => $q->where('t.route_id', $routePkFilter))
            ->select(['st.departure_time', 'r.id as route_pk', 'r.route_short_name', 't.direction_id'])
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'departure_time' => (string) $row->departure_time,
                'route' => [
                    'id' => (int) $row->route_pk,
                    'short_name' => (string) $row->route_short_name,
                ],
                'direction_id' => $row->direction_id !== null ? (int) $row->direction_id : null,
            ];
        }

        usort(
            $items,
            fn (array $a, array $b): int => $this->calendar->gtfsTimeToSeconds($a['departure_time']) <=> $this->calendar->gtfsTimeToSeconds($b['departure_time'])
        );

        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $key = $item['route']['id'].'|'.$item['departure_time'].'|'.($item['direction_id'] ?? 'n');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return array_slice($unique, 0, $limit);
    }
}
