<?php

namespace App\Services\Gtfs;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class GtfsCalendar
{
    public function toLocalTime(DateTimeInterface $date): CarbonInterface
    {
        if ($date instanceof CarbonInterface) {
            return $date->copy()->setTimezone('Europe/Warsaw');
        }

        return Carbon::instance($date)->setTimezone('Europe/Warsaw');
    }

    public function wallClockToServiceSeconds(DateTimeInterface $date): int
    {
        return $this->toLocalTime($date)->secondsSinceMidnight();
    }

    public function gtfsTimeToSeconds(string $time): int
    {
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);
        $seconds = (int) ($parts[2] ?? 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public function secondsToGtfsTime(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining);
    }

    public function activeServiceIds(DateTimeInterface $date): array
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
}

