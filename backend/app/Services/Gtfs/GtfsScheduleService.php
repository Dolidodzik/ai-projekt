<?php

namespace App\Services\Gtfs;

use DateTimeInterface;

final class GtfsScheduleService
{
    public function __construct(
        private readonly GtfsRouteCatalog $catalog,
        private readonly GtfsRoutePatternBuilder $patternBuilder,
        private readonly GtfsDepartureBoard $departureBoard,
        private readonly GtfsTripDetailsPresenter $tripDetails,
    ) {
    }

    public function listRoutes(): array
    {
        return $this->catalog->listRoutes();
    }

    public function listStops(int $limit = 500, ?string $query = null): array
    {
        return $this->catalog->listStops($limit, $query);
    }

    public function routePattern(int $routePk): ?array
    {
        return $this->patternBuilder->routePattern($routePk);
    }

    public function routeStopDepartures(
        int $routePk,
        int $stopPk,
        ?int $directionKey,
        DateTimeInterface $date,
        ?int $tripPatternId = null,
        bool $useTripEndpoints = false
    ): array {
        return $this->departureBoard->routeStopDepartures(
            $routePk,
            $stopPk,
            $directionKey,
            $date,
            $tripPatternId,
            $useTripEndpoints
        );
    }

    public function stopBoardDepartures(int $stopPk, DateTimeInterface $date, ?int $routePkFilter = null, int $limit = 400): array
    {
        return $this->departureBoard->stopBoardDepartures($stopPk, $date, $routePkFilter, $limit);
    }

    public function tripDetails(int $tripId): ?array
    {
        return $this->tripDetails->tripDetails($tripId);
    }
}
