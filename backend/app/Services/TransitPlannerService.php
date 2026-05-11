<?php

namespace App\Services;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class TransitPlannerService
{
    private ?array $egressDistanceByStop = null;

    private const MIN_TRANSFER_SECONDS = 180;

    private const MAX_TRANSFER_SECONDS = 900;

    private const NEAR_DEPARTURE_SECONDS = 120;

    private const WALK_RADIUS_METERS = 300;

    private const NEARBY_STOPS_LIMIT = 0;

    private const SEARCH_WINDOW_SECONDS = 10800;

    private const SEARCH_WINDOW_FALLBACK_SECONDS = 28800;

    private const SCHEDULES_PER_PATTERN = 6;

    private const ONE_TRANSFER_ROW_LIMIT = 2500;

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
        return $this->wallClockToServiceSeconds($date);
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
        $fromStopIds = $this->normalizeStopIds($fromStopIds);
        $toStopIds = $this->normalizeStopIds($toStopIds);
        if ($fromStopIds === [] || $toStopIds === [] || array_intersect($fromStopIds, $toStopIds) !== []) {
            return [];
        }

        $this->egressDistanceByStop = $egressDistanceByStop;

        $departAt ??= now();
        $localDepartAt = $this->toLocalTime($departAt);
        $activeServices = $this->activeServiceIds($localDepartAt);
        if ($activeServices === []) {
            return [];
        }

        $earliestSeconds = $this->wallClockToServiceSeconds($localDepartAt);
        $windowStartSeconds = $earliestSeconds;
        if ($accessSecondsByStop !== null && $accessSecondsByStop !== []) {
            $windowStartSeconds = min($accessSecondsByStop);
        }

        $windowEndSeconds = $earliestSeconds + self::SEARCH_WINDOW_SECONDS;
        $candidates = $this->collectScheduledCandidates(
            $fromStopIds,
            $toStopIds,
            $maxTransfers,
            $windowStartSeconds,
            $windowEndSeconds,
            $activeServices,
            $accessSecondsByStop,
            $earliestSeconds
        );

        if ($candidates === []) {
            $candidates = $this->collectScheduledCandidates(
                $fromStopIds,
                $toStopIds,
                $maxTransfers,
                $windowStartSeconds,
                $earliestSeconds + self::SEARCH_WINDOW_FALLBACK_SECONDS,
                $activeServices,
                $accessSecondsByStop,
                $earliestSeconds
            );
        }

        if ($candidates === []) {
            return [];
        }

        $candidatePaths = $this->selectTopCandidates(
            $this->collapseNearDeparturesForPattern(
                $this->removeDominatedTransferPaths(array_values($candidates))
            ),
            $limit
        );

        $formatted = array_map(
            fn (array $legs): array => $this->formatTransitPath($legs),
            $candidatePaths
        );

        $this->egressDistanceByStop = null;

        return $formatted;
    }

    public function listRoutes(): array
    {
        return DB::table('gtfs_routes')
            ->select('id', 'route_id', 'route_short_name', 'route_long_name', 'route_type')
            ->orderBy('route_short_name')
            ->get()
            ->map(static function ($row): array {
                return [
                    'id' => (int) $row->id,
                    'route_id' => (string) $row->route_id,
                    'short_name' => (string) $row->route_short_name,
                    'long_name' => $row->route_long_name !== null ? (string) $row->route_long_name : null,
                    'route_type' => (int) $row->route_type,
                ];
            })
            ->all();
    }

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

        $stops = DB::table('gtfs_stop_times as st')
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
            ->map(static function ($row): array {
                return [
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
                ];
            })
            ->all();

        $shape = [];
        if ($trip->shape_id !== null) {
            $shape = DB::table('gtfs_shapes')
                ->select('shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence')
                ->where('shape_id', $trip->shape_id)
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

    private function activeServiceIds(DateTimeInterface $date): array
    {
        $localDate = $this->toLocalTime($date);
        $dateString = $localDate->format('Y-m-d');
        $dayColumns = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $dayColumn = $dayColumns[(int) $localDate->format('w')];

        $calendarServices = DB::table('gtfs_calendars')
            ->where('start_date', '<=', $dateString)
            ->where('end_date', '>=', $dateString)
            ->where($dayColumn, '1')
            ->pluck('service_id')
            ->all();

        $added = DB::table('gtfs_calendar_dates')
            ->where('date', $dateString)
            ->where('exception_type', 1)
            ->whereNotNull('service_id')
            ->pluck('service_id')
            ->all();

        $removed = DB::table('gtfs_calendar_dates')
            ->where('date', $dateString)
            ->where('exception_type', 2)
            ->whereNotNull('service_id')
            ->pluck('service_id')
            ->all();

        return array_values(array_unique(array_merge(
            array_diff($calendarServices, $removed),
            $added
        )));
    }

    private function collectScheduledCandidates(
        array $fromStopIds,
        array $toStopIds,
        int $maxTransfers,
        int $windowStartSeconds,
        int $windowEndSeconds,
        array $activeServices,
        ?array $accessSecondsByStop,
        int $defaultAccessSeconds
    ): array {
        $candidates = [];
        $earliestDeparture = $this->secondsToGtfsTime($windowStartSeconds);
        $latestDeparture = $this->secondsToGtfsTime($windowEndSeconds);

        foreach ($this->collectDirectLegs(
            $fromStopIds,
            $toStopIds,
            $earliestDeparture,
            $latestDeparture,
            $activeServices,
            $accessSecondsByStop,
            $defaultAccessSeconds
        ) as $legs) {
            $this->rememberCandidate($candidates, $legs);
        }

        if ($maxTransfers >= 1) {
            foreach ($this->collectOneTransferLegs(
                $fromStopIds,
                $toStopIds,
                $earliestDeparture,
                $latestDeparture,
                $activeServices,
                $accessSecondsByStop,
                $defaultAccessSeconds
            ) as $legs) {
                $this->rememberCandidate($candidates, $legs);
            }
        }

        if ($maxTransfers >= 2) {
            foreach ($this->collectTwoTransferLegs($fromStopIds, $toStopIds, $windowStartSeconds, $windowEndSeconds, $activeServices) as $legs) {
                if ($this->isAccessibleDeparture($legs[0], $fromStopIds, $accessSecondsByStop, $defaultAccessSeconds)) {
                    $this->rememberCandidate($candidates, $legs);
                }
            }
        }

        return $candidates;
    }

    private function collectDirectLegs(
        array $fromStopIds,
        array $toStopIds,
        string $earliestDeparture,
        string $latestDeparture,
        array $activeServices,
        ?array $accessSecondsByStop = null,
        int $defaultAccessSeconds = 0
    ): array {
        $rows = DB::table('gtfs_stop_times as from_st')
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
                'from_st.stop_id as from_stop_id',
                'to_st.stop_id as to_stop_id',
                'from_st.departure_time as from_departure_time',
                'to_st.arrival_time as to_arrival_time',
                'from_st.stop_sequence as from_sequence',
                'to_st.stop_sequence as to_sequence',
            ])
            ->whereIn('from_st.stop_id', $fromStopIds)
            ->whereIn('to_st.stop_id', $toStopIds)
            ->whereIn('t.service_id', $activeServices)
            ->whereColumn('from_st.stop_sequence', '<', 'to_st.stop_sequence')
            ->where('from_st.departure_time', '>=', $earliestDeparture)
            ->where('from_st.departure_time', '<=', $latestDeparture);
        $this->applyAccessibleDepartureConstraint(
            $rows,
            $fromStopIds,
            $accessSecondsByStop,
            $defaultAccessSeconds,
            'from_st.stop_id',
            'from_st.departure_time'
        );

        $rows = $rows
            ->orderBy('from_st.departure_time')
            ->limit(self::SCHEDULES_PER_PATTERN * 4)
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $legs = [$this->makeLegFromRow($row)];
            if (! $this->isAccessibleDeparture($legs[0], $fromStopIds, $accessSecondsByStop, $defaultAccessSeconds)) {
                continue;
            }

            if ($this->rememberPatternSchedule($results, $legs)) {
                continue;
            }

            $results[] = $legs;
        }

        return $results;
    }

    private function collectOneTransferLegs(
        array $fromStopIds,
        array $toStopIds,
        string $earliestDeparture,
        string $latestDeparture,
        array $activeServices,
        ?array $accessSecondsByStop = null,
        int $defaultAccessSeconds = 0
    ): array {
        $rows = $this->oneTransferPatternQuery($fromStopIds, $toStopIds, $activeServices)
            ->where('a.departure_time', '>=', $earliestDeparture)
            ->where('a.departure_time', '<=', $latestDeparture);
        $this->applyAccessibleDepartureConstraint(
            $rows,
            $fromStopIds,
            $accessSecondsByStop,
            $defaultAccessSeconds,
            'a.stop_id',
            'a.departure_time'
        );

        $rows = $rows
            ->orderBy('a.departure_time')
            ->limit(self::ONE_TRANSFER_ROW_LIMIT)
            ->get();

        $results = [];

        foreach ($rows as $row) {
            if (! $this->passesTransferGap((string) $row->leg2_departure_time, (string) $row->leg1_arrival_time)) {
                continue;
            }

            $legs = $this->makeTwoLegsFromTransferRow($row);
            if (! $this->isAccessibleDeparture($legs[0], $fromStopIds, $accessSecondsByStop, $defaultAccessSeconds)) {
                continue;
            }

            if ($this->rememberPatternSchedule($results, $legs)) {
                continue;
            }

            $results[] = $legs;
        }

        return $results;
    }

    private function oneTransferBaseQuery(array $fromStopIds, array $toStopIds, array $activeServices)
    {
        return DB::table('gtfs_stop_times as a')
            ->join('gtfs_stop_times as b', 'b.trip_id', '=', 'a.trip_id')
            ->join('gtfs_stop_times as c', 'c.stop_id', '=', 'b.stop_id')
            ->join('gtfs_stop_times as d', 'd.trip_id', '=', 'c.trip_id')
            ->join('gtfs_trips as t1', 't1.id', '=', 'a.trip_id')
            ->join('gtfs_trips as t2', 't2.id', '=', 'c.trip_id')
            ->join('gtfs_routes as r1', 'r1.id', '=', 't1.route_id')
            ->join('gtfs_routes as r2', 'r2.id', '=', 't2.route_id')
            ->whereIn('a.stop_id', $fromStopIds)
            ->whereIn('d.stop_id', $toStopIds)
            ->whereIn('t1.service_id', $activeServices)
            ->whereIn('t2.service_id', $activeServices)
            ->whereColumn('a.stop_sequence', '<', 'b.stop_sequence')
            ->whereColumn('c.stop_sequence', '<', 'd.stop_sequence')
            ->whereColumn('a.trip_id', '<>', 'c.trip_id')
            ->whereRaw('c.departure_time >= b.arrival_time');
    }

    private function oneTransferPatternQuery(array $fromStopIds, array $toStopIds, array $activeServices)
    {
        return $this->oneTransferBaseQuery($fromStopIds, $toStopIds, $activeServices)
            ->select([
                't1.id as trip1_pk',
                't1.trip_id as trip1_id',
                't2.id as trip2_pk',
                't2.trip_id as trip2_id',
                'r1.id as route1_pk',
                'r1.route_id as route1_id',
                'r1.route_short_name as route1_short_name',
                'r1.route_long_name as route1_long_name',
                'r2.id as route2_pk',
                'r2.route_id as route2_id',
                'r2.route_short_name as route2_short_name',
                'r2.route_long_name as route2_long_name',
                'a.stop_id as from_stop_id',
                'b.stop_id as transfer_stop_id',
                'd.stop_id as to_stop_id',
                'a.departure_time as leg1_departure_time',
                'b.arrival_time as leg1_arrival_time',
                'c.departure_time as leg2_departure_time',
                'd.arrival_time as leg2_arrival_time',
                'a.stop_sequence as leg1_from_sequence',
                'b.stop_sequence as leg1_to_sequence',
                'c.stop_sequence as leg2_from_sequence',
                'd.stop_sequence as leg2_to_sequence',
            ]);
    }

    private function collectTwoTransferLegs(
        array $fromStopIds,
        array $toStopIds,
        int $windowStartSeconds,
        int $windowEndSeconds,
        array $activeServices
    ): array {
        $connections = $this->buildConnections($windowStartSeconds, $windowEndSeconds, $activeServices);
        if ($connections === []) {
            return [];
        }

        $earliestArrival = [];
        $previousConnection = [];
        $transferCount = [];

        foreach ($fromStopIds as $stopId) {
            $earliestArrival[$stopId] = $windowStartSeconds;
            $previousConnection[$stopId] = null;
            $transferCount[$stopId] = -1;
        }

        $results = [];

        foreach ($connections as $connection) {
            $depStopId = $connection['dep_stop_id'];
            if (! array_key_exists($depStopId, $earliestArrival)) {
                continue;
            }

            $requiredDeparture = $earliestArrival[$depStopId];
            $previous = $previousConnection[$depStopId];
            if ($previous !== null && $previous['trip_pk'] !== $connection['trip_pk']) {
                $transferGap = $connection['dep_seconds'] - $previous['arr_seconds'];
                if ($transferGap < self::MIN_TRANSFER_SECONDS || $transferGap > self::MAX_TRANSFER_SECONDS) {
                    continue;
                }

                $requiredDeparture = $previous['arr_seconds'] + self::MIN_TRANSFER_SECONDS;
            }

            if ($connection['dep_seconds'] < $requiredDeparture) {
                continue;
            }

            $arrStopId = $connection['arr_stop_id'];
            $nextTransfers = ($previous === null || $previous['trip_pk'] === $connection['trip_pk'])
                ? $transferCount[$depStopId]
                : $transferCount[$depStopId] + 1;
            if ($nextTransfers > 2) {
                continue;
            }

            if (in_array($arrStopId, $toStopIds, true)) {
                $trail = $this->buildTrail($connection, $previousConnection, $depStopId);
                if (count($this->connectionsToLegs($trail)) === 3) {
                    $results[] = $this->connectionsToLegs($trail);
                }
            }

            if (! array_key_exists($arrStopId, $earliestArrival) || $connection['arr_seconds'] < $earliestArrival[$arrStopId]) {
                $earliestArrival[$arrStopId] = $connection['arr_seconds'];
                $previousConnection[$arrStopId] = $connection;
                $transferCount[$arrStopId] = $nextTransfers;
            }
        }

        return $results;
    }

    private function buildTrail(array $connection, array $previousConnection, int $depStopId): array
    {
        $trail = [$connection];
        $currentStopId = $depStopId;

        while (($previousConnection[$currentStopId] ?? null) !== null) {
            $previous = $previousConnection[$currentStopId];
            $trail[] = $previous;
            $currentStopId = $previous['dep_stop_id'];
        }

        return array_reverse($trail);
    }

    private function applyAccessibleDepartureConstraint(
        $query,
        array $fromStopIds,
        ?array $accessSecondsByStop,
        int $defaultAccessSeconds,
        string $stopColumn,
        string $departureColumn
    ): void {
        if ($accessSecondsByStop === null || $accessSecondsByStop === []) {
            return;
        }

        $query->where(function ($builder) use (
            $fromStopIds,
            $accessSecondsByStop,
            $defaultAccessSeconds,
            $stopColumn,
            $departureColumn
        ): void {
            foreach ($fromStopIds as $stopId) {
                $requiredSeconds = $accessSecondsByStop[$stopId] ?? $defaultAccessSeconds;
                $builder->orWhere(function ($nested) use (
                    $stopId,
                    $requiredSeconds,
                    $stopColumn,
                    $departureColumn
                ): void {
                    $nested->where($stopColumn, $stopId)
                        ->where($departureColumn, '>=', $this->secondsToGtfsTime($requiredSeconds));
                });
            }
        });
    }

    private function isAccessibleDeparture(
        array $firstLeg,
        array $fromStopIds,
        ?array $accessSecondsByStop,
        int $defaultAccessSeconds
    ): bool {
        if (! in_array((int) $firstLeg['from_stop_id'], $fromStopIds, true)) {
            return false;
        }

        $departureSeconds = $this->gtfsTimeToSeconds($firstLeg['from_departure_time']);
        $requiredSeconds = $accessSecondsByStop[$firstLeg['from_stop_id']] ?? $defaultAccessSeconds;

        return $departureSeconds >= $requiredSeconds;
    }

    private function makeLegFromRow(object $row): array
    {
        return [
            'trip_id' => (string) $row->trip_id,
            'trip_pk' => (int) $row->trip_pk,
            'from_stop_id' => (int) $row->from_stop_id,
            'to_stop_id' => (int) $row->to_stop_id,
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

    private function buildConnections(int $earliestSeconds, int $windowEndSeconds, array $activeServices): array
    {
        $rows = DB::table('gtfs_stop_times as st')
            ->join('gtfs_trips as t', 't.id', '=', 'st.trip_id')
            ->join('gtfs_routes as r', 'r.id', '=', 't.route_id')
            ->select([
                'st.trip_id',
                'st.stop_id',
                'st.stop_sequence',
                'st.departure_time',
                'st.arrival_time',
                't.id as trip_pk',
                't.trip_id as trip_code',
                'r.id as route_pk',
                'r.route_id',
                'r.route_short_name',
                'r.route_long_name',
            ])
            ->whereIn('t.service_id', $activeServices)
            ->where('st.departure_time', '>=', $this->secondsToGtfsTime(max(0, $earliestSeconds - 300)))
            ->where('st.departure_time', '<=', $this->secondsToGtfsTime($windowEndSeconds))
            ->orderBy('st.trip_id')
            ->orderBy('st.stop_sequence')
            ->limit(200000)
            ->get();

        $connections = [];
        $currentTripId = null;
        $previous = null;

        foreach ($rows as $row) {
            if ($currentTripId !== (int) $row->trip_id) {
                $currentTripId = (int) $row->trip_id;
                $previous = null;
            }

            if ($previous !== null) {
                $connections[] = [
                    'dep_stop_id' => (int) $previous->stop_id,
                    'arr_stop_id' => (int) $row->stop_id,
                    'dep_seconds' => $this->gtfsTimeToSeconds((string) $previous->departure_time),
                    'arr_seconds' => $this->gtfsTimeToSeconds((string) $row->arrival_time),
                    'from_sequence' => (int) $previous->stop_sequence,
                    'to_sequence' => (int) $row->stop_sequence,
                    'trip_pk' => (int) $row->trip_pk,
                    'trip_id' => (string) $row->trip_code,
                    'route' => [
                        'id' => (int) $row->route_pk,
                        'route_id' => (string) $row->route_id,
                        'short_name' => (string) $row->route_short_name,
                        'long_name' => $row->route_long_name !== null ? (string) $row->route_long_name : null,
                    ],
                ];
            }

            $previous = $row;
        }

        usort($connections, fn (array $left, array $right): int => $left['dep_seconds'] <=> $right['dep_seconds']);

        return $connections;
    }

    private function connectionsToLegs(array $trail): array
    {
        $legs = [];
        $index = 0;

        while ($index < count($trail)) {
            $tripPk = $trail[$index]['trip_pk'];
            $end = $index;
            while ($end < count($trail) && $trail[$end]['trip_pk'] === $tripPk) {
                $end++;
            }

            $first = $trail[$index];
            $last = $trail[$end - 1];
            $legs[] = [
                'trip_id' => $first['trip_id'],
                'trip_pk' => $tripPk,
                'from_stop_id' => $first['dep_stop_id'],
                'to_stop_id' => $last['arr_stop_id'],
                'route' => $first['route'],
                'from_departure_time' => $this->secondsToGtfsTime($first['dep_seconds']),
                'to_arrival_time' => $this->secondsToGtfsTime($last['arr_seconds']),
                'stops_span' => $last['to_sequence'] - $first['from_sequence'],
            ];
            $index = $end;
        }

        return $legs;
    }

    private function normalizeStopIds(int|array $stopIds): array
    {
        $ids = is_array($stopIds) ? $stopIds : [$stopIds];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function rememberCandidate(array &$candidates, array $legs): void
    {
        $signature = $this->pathSignature($legs);
        if (! isset($candidates[$signature])) {
            $candidates[$signature] = $legs;
        }
    }

    private function rememberPatternSchedule(array $results, array $legs): bool
    {
        $signature = $this->pathSignature($legs);
        $pattern = implode('->', array_map(
            static fn (array $leg): string => $leg['route']['short_name'],
            $legs
        ));
        $patternCount = 0;

        foreach ($results as $existingLegs) {
            if ($this->pathSignature($existingLegs) === $signature) {
                return true;
            }

            $existingPattern = implode('->', array_map(
                static fn (array $leg): string => $leg['route']['short_name'],
                $existingLegs
            ));
            if ($existingPattern === $pattern) {
                $patternCount++;
            }
        }

        return $patternCount >= self::SCHEDULES_PER_PATTERN;
    }

    private function passesTransferGap(string $laterTime, string $earlierTime): bool
    {
        $laterSeconds = $this->gtfsTimeToSeconds($laterTime);
        $earlierSeconds = $this->gtfsTimeToSeconds($earlierTime);
        if ($laterSeconds < $earlierSeconds) {
            $laterSeconds += 86400;
        }

        $gap = $laterSeconds - $earlierSeconds;

        return $gap >= self::MIN_TRANSFER_SECONDS && $gap <= self::MAX_TRANSFER_SECONDS;
    }

    private function removeDominatedTransferPaths(array $paths): array
    {
        $directPaths = array_values(array_filter(
            $paths,
            static fn (array $legs): bool => count($legs) === 1
        ));

        return array_values(array_filter(
            $paths,
            fn (array $legs): bool => count($legs) < 2 || ! $this->isDominatedByDirectLeg($legs, $directPaths)
        ));
    }

    private function collapseNearDeparturesForPattern(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        $grouped = [];
        foreach ($paths as $legs) {
            $pattern = implode('->', array_map(
                static fn (array $leg): string => $leg['route']['short_name'],
                $legs
            ));
            $grouped[$pattern][] = $legs;
        }

        $collapsed = [];
        foreach ($grouped as $patternPaths) {
            usort(
                $patternPaths,
                fn (array $left, array $right): int => $this->gtfsTimeToSeconds($left[0]['from_departure_time'])
                    <=> $this->gtfsTimeToSeconds($right[0]['from_departure_time'])
            );

            $lastKeptDeparture = null;
            foreach ($patternPaths as $legs) {
                $departureSeconds = $this->gtfsTimeToSeconds($legs[0]['from_departure_time']);
                if ($lastKeptDeparture !== null
                    && $departureSeconds - $lastKeptDeparture < self::NEAR_DEPARTURE_SECONDS) {
                    continue;
                }

                $collapsed[] = $legs;
                $lastKeptDeparture = $departureSeconds;
            }
        }

        usort($collapsed, fn (array $left, array $right): int => $this->compareCandidates($left, $right));

        return $collapsed;
    }

    private function isDominatedByDirectLeg(array $transferLegs, array $directPaths): bool
    {
        $firstLeg = $transferLegs[0];
        $lastLeg = $transferLegs[array_key_last($transferLegs)];
        $transferArrival = $this->pathArrivalSeconds($transferLegs);
        $transferDestinationRank = $this->pathDestinationRank($transferLegs);
        $routesToCheck = array_values(array_unique([
            $firstLeg['route']['short_name'],
            $lastLeg['route']['short_name'],
        ]));

        foreach ($directPaths as $directLegs) {
            $directLeg = $directLegs[0];
            if (! in_array($directLeg['route']['short_name'], $routesToCheck, true)) {
                continue;
            }

            if ((int) $directLeg['from_stop_id'] !== (int) $firstLeg['from_stop_id']) {
                continue;
            }

            if ($this->pathArrivalSeconds($directLegs) > $transferArrival) {
                continue;
            }

            $directDestinationRank = $this->pathDestinationRank($directLegs);
            if ($this->egressDistanceByStop !== null) {
                if ($directDestinationRank <= $transferDestinationRank) {
                    return true;
                }

                continue;
            }

            if ((int) $directLeg['to_stop_id'] === (int) $lastLeg['to_stop_id']) {
                return true;
            }
        }

        return false;
    }

    private function makeTwoLegsFromTransferRow(object $row): array
    {
        return [
            [
                'trip_id' => (string) $row->trip1_id,
                'trip_pk' => (int) $row->trip1_pk,
                'from_stop_id' => (int) $row->from_stop_id,
                'to_stop_id' => (int) $row->transfer_stop_id,
                'route' => [
                    'id' => (int) $row->route1_pk,
                    'route_id' => (string) $row->route1_id,
                    'short_name' => (string) $row->route1_short_name,
                    'long_name' => $row->route1_long_name !== null ? (string) $row->route1_long_name : null,
                ],
                'from_departure_time' => (string) $row->leg1_departure_time,
                'to_arrival_time' => (string) $row->leg1_arrival_time,
                'stops_span' => (int) $row->leg1_to_sequence - (int) $row->leg1_from_sequence,
            ],
            [
                'trip_id' => (string) $row->trip2_id,
                'trip_pk' => (int) $row->trip2_pk,
                'from_stop_id' => (int) $row->transfer_stop_id,
                'to_stop_id' => (int) $row->to_stop_id,
                'route' => [
                    'id' => (int) $row->route2_pk,
                    'route_id' => (string) $row->route2_id,
                    'short_name' => (string) $row->route2_short_name,
                    'long_name' => $row->route2_long_name !== null ? (string) $row->route2_long_name : null,
                ],
                'from_departure_time' => (string) $row->leg2_departure_time,
                'to_arrival_time' => (string) $row->leg2_arrival_time,
                'stops_span' => (int) $row->leg2_to_sequence - (int) $row->leg2_from_sequence,
            ],
        ];
    }

    private function selectTopCandidates(array $candidatePaths, int $limit): array
    {
        if ($candidatePaths === []) {
            return [];
        }

        usort($candidatePaths, fn (array $left, array $right): int => $this->compareCandidates($left, $right));

        $selected = [];
        $selectedSignatures = [];

        foreach ($candidatePaths as $legs) {
            if (count($selected) >= $limit) {
                break;
            }

            $signature = $this->pathSignature($legs);
            if (isset($selectedSignatures[$signature])) {
                continue;
            }

            $selectedSignatures[$signature] = true;
            $selected[] = $legs;
        }

        return $selected;
    }

    private function compareCandidates(array $left, array $right): int
    {
        $leftArrival = $this->pathArrivalSeconds($left);
        $rightArrival = $this->pathArrivalSeconds($right);
        if ($leftArrival !== $rightArrival) {
            return $leftArrival <=> $rightArrival;
        }

        $leftTransfers = count($left) - 1;
        $rightTransfers = count($right) - 1;
        if ($leftTransfers !== $rightTransfers) {
            return $leftTransfers <=> $rightTransfers;
        }

        $leftDestinationRank = $this->pathDestinationRank($left);
        $rightDestinationRank = $this->pathDestinationRank($right);
        if ($leftDestinationRank !== $rightDestinationRank) {
            return $leftDestinationRank <=> $rightDestinationRank;
        }

        return $this->pathDurationSeconds($left) <=> $this->pathDurationSeconds($right);
    }

    private function pathArrivalSeconds(array $legs): int
    {
        $start = $this->gtfsTimeToSeconds($legs[0]['from_departure_time']);
        $end = $this->gtfsTimeToSeconds($legs[array_key_last($legs)]['to_arrival_time']);
        if ($end < $start) {
            $end += 86400;
        }

        return $end;
    }

    private function pathDestinationRank(array $legs): int
    {
        if ($this->egressDistanceByStop === null) {
            return 0;
        }

        $lastStopId = (int) $legs[array_key_last($legs)]['to_stop_id'];

        return $this->egressDistanceByStop[$lastStopId] ?? PHP_INT_MAX;
    }

    private function pathSignature(array $legs): string
    {
        $lines = implode('->', array_map(
            static fn (array $leg): string => $leg['route']['short_name'],
            $legs
        ));

        return $lines.':'.$legs[0]['from_departure_time'];
    }

    private function pathScore(array $legs): float
    {
        $transfers = max(0, count($legs) - 1);

        return $this->pathDurationSeconds($legs) / ($transfers + 1);
    }

    private function pathDurationSeconds(array $legs): int
    {
        $start = $this->gtfsTimeToSeconds($legs[0]['from_departure_time']);
        $end = $this->gtfsTimeToSeconds($legs[array_key_last($legs)]['to_arrival_time']);
        if ($end < $start) {
            $end += 86400;
        }

        return $end - $start;
    }

    private function formatTransitPath(array $legs): array
    {
        if (count($legs) === 1) {
            $leg = $this->legPayload($legs[0]);

            return [
                'type' => 'direct',
                'trip_id' => $leg['trip_id'],
                'trip_pk' => $leg['trip_pk'],
                'from_stop_id' => $leg['from_stop_id'],
                'to_stop_id' => $leg['to_stop_id'],
                'route' => $leg['route'],
                'from_departure_time' => $leg['from_departure_time'],
                'to_arrival_time' => $leg['to_arrival_time'],
                'stops_span' => $leg['stops_span'],
            ];
        }

        if (count($legs) === 2) {
            return [
                'type' => 'one_transfer',
                'transfer_stop_id' => (int) $legs[0]['to_stop_id'],
                'legs' => array_map(fn (array $leg): array => $this->legPayload($leg), $legs),
            ];
        }

        return [
            'type' => 'multi_transfer',
            'transfer_stop_ids' => array_map(
                fn (array $leg): int => (int) $leg['to_stop_id'],
                array_slice($legs, 0, -1)
            ),
            'legs' => array_map(fn (array $leg): array => $this->legPayload($leg), $legs),
        ];
    }

    private function legPayload(array $leg): array
    {
        return [
            'trip_id' => $leg['trip_id'],
            'trip_pk' => $leg['trip_pk'],
            'from_stop_id' => (int) $leg['from_stop_id'],
            'to_stop_id' => (int) $leg['to_stop_id'],
            'route' => $leg['route'],
            'from_departure_time' => $leg['from_departure_time'],
            'to_arrival_time' => $leg['to_arrival_time'],
            'stops_span' => $leg['stops_span'],
        ];
    }

    private function toLocalTime(DateTimeInterface $date): CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date->copy()->setTimezone('Europe/Warsaw');
        }

        return \Illuminate\Support\Carbon::instance($date)->setTimezone('Europe/Warsaw');
    }

    private function wallClockToServiceSeconds(DateTimeInterface $date): int
    {
        return $this->toLocalTime($date)->secondsSinceMidnight();
    }

    private function gtfsTimeToSeconds(string $time): int
    {
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);
        $seconds = (int) ($parts[2] ?? 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    private function secondsToGtfsTime(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
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
