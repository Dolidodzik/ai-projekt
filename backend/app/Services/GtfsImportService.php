<?php

namespace App\Services;

use App\Models\GtfsFeedVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class GtfsImportService
{
    public function sync(bool $force = false): array
    {
        $url = (string) config('gtfs.feed_url');
        $workDir = (string) config('gtfs.work_dir');
        $disk = Storage::disk('local');
        $disk->makeDirectory($workDir);

        $zipRel = $workDir.'/latest.zip';
        $extractRel = $workDir.'/extract';
        $version = null;

        try {
            if (! Schema::hasTable('gtfs_feed_versions')) {
                throw new RuntimeException(
                    'Missing gtfs_feed_versions table. Run php artisan migrate first.'
                );
            }

            $this->downloadZip($url, $zipRel);
            $extractPath = storage_path('app/'.$extractRel);
            if (is_dir($extractPath)) {
                $this->deleteDirectory($extractPath);
            }
            $disk->makeDirectory($extractRel);

            $zipFull = storage_path('app/'.$zipRel);
            $this->extractZip($zipFull, $extractPath);

            $dataDir = $this->resolveDataDir($extractPath);
            $version = $this->readFeedVersion($dataDir);

            if ($version === null || $version === '') {
                $version = 'unknown-'.date('Y-m-d\TH:i:s');
            }

            $latest = GtfsFeedVersion::where('status', 'success')
                ->orderByDesc('id')
                ->value('feed_version');

            if (! $force && $latest !== null && trim((string) $latest) === trim($version)) {
                GtfsFeedVersion::create([
                    'feed_version' => $version,
                    'source_url' => $url,
                    'status' => 'skipped',
                    'message' => null,
                ]);

                return [
                    'status' => 'skipped',
                    'feed_version' => $version,
                    'message' => null,
                ];
            }

            DB::transaction(function () use ($dataDir, $version, $url) {
                $this->purgeGtfsRelated();
                $this->importCalendars($dataDir);
                $this->importCalendarDates($dataDir);
                $routeMap = $this->importRoutes($dataDir);
                $stopMap = $this->importStops($dataDir);
                $this->importShapeGroups($dataDir);
                $this->importShapes($dataDir);
                $tripMap = $this->importTrips($dataDir, $routeMap);
                $this->importStopTimes($dataDir, $tripMap, $stopMap);

                GtfsFeedVersion::create([
                    'feed_version' => $version,
                    'source_url' => $url,
                    'status' => 'success',
                    'message' => null,
                ]);
            });

            return [
                'status' => 'imported',
                'feed_version' => $version,
                'message' => null,
            ];
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            try {
                GtfsFeedVersion::create([
                    'feed_version' => $version !== null && $version !== '' ? (string) $version : 'error',
                    'source_url' => $url,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ]);
            } catch (Throwable) {
            }

            throw $e;
        }
    }

    protected function downloadZip(string $url, string $relativePath): void
    {
        $full = storage_path('app/'.$relativePath);
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $response = Http::timeout(600)->sink($full)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('GTFS download failed: HTTP '.$response->status());
        }
    }

    protected function extractZip(string $zipFull, string $targetDir): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipFull) !== true) {
            throw new RuntimeException('Cannot open GTFS zip');
        }
        $zip->extractTo($targetDir);
        $zip->close();
    }

    protected function resolveDataDir(string $extractRoot): string
    {
        $entries = array_values(array_filter(scandir($extractRoot) ?: [], fn ($e) => $e !== '.' && $e !== '..'));
        if (count($entries) === 1 && is_dir($extractRoot.'/'.$entries[0])) {
            return $extractRoot.'/'.$entries[0];
        }

        return $extractRoot;
    }

    protected function readFeedVersion(string $dataDir): ?string
    {
        $path = $this->findFile($dataDir, 'feed_info.txt');
        if ($path === null) {
            return null;
        }

        $parsed = $this->readCsvAssoc($path);
        if ($parsed === null) {
            return null;
        }

        [$header, $first] = $parsed;
        $map = array_combine($header, $first);
        if ($map === false) {
            return null;
        }

        if (! empty($map['feed_version'])) {
            return trim((string) $map['feed_version']);
        }

        $a = $map['feed_start_date'] ?? '';
        $b = $map['feed_end_date'] ?? '';
        if ($a !== '' || $b !== '') {
            return trim((string) $a).'-'.trim((string) $b);
        }

        return null;
    }

    protected function findFile(string $dir, string $name): ?string
    {
        $path = $dir.'/'.$name;
        if (is_readable($path)) {
            return $path;
        }

        foreach (glob($dir.'/*.txt') ?: [] as $f) {
            if (strcasecmp(basename($f), $name) === 0) {
                return $f;
            }
        }

        return null;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}|null
     */
    protected function readCsvAssoc(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return null;
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return null;
        }
        $header[0] = isset($header[0]) ? preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) : '';
        $row = fgetcsv($handle);
        fclose($handle);
        if ($row === false) {
            return null;
        }

        return [$header, $row];
    }

    protected function purgeGtfsRelated(): void
    {
        DB::table('ride_history')->delete();

        DB::table('gtfs_stop_times')->delete();
        DB::table('gtfs_trips')->delete();
        DB::table('gtfs_calendar_dates')->delete();
        DB::table('gtfs_shapes')->delete();
        DB::table('gtfs_shape_groups')->delete();
        DB::table('gtfs_calendars')->delete();
        DB::table('gtfs_routes')->delete();
        DB::table('gtfs_stops')->delete();
    }

    /**
     * @return array<string, int>
     */
    protected function importStops(string $dataDir): array
    {
        $path = $this->findFile($dataDir, 'stops.txt');
        if ($path === null) {
            throw new RuntimeException('stops.txt missing');
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('stops.txt unreadable');
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new RuntimeException('stops.txt empty');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');

        $batch = [];
        $map = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false || empty($r['stop_id'])) {
                continue;
            }
            $batch[] = [
                'stop_id' => (string) $r['stop_id'],
                'stop_name' => (string) ($r['stop_name'] ?? ''),
                'stop_lat' => (float) ($r['stop_lat'] ?? 0),
                'stop_lon' => (float) ($r['stop_lon'] ?? 0),
            ];
            if (count($batch) >= 800) {
                DB::table('gtfs_stops')->insert($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('gtfs_stops')->insert($batch);
        }

        $pairs = DB::table('gtfs_stops')->select('id', 'stop_id')->get();
        foreach ($pairs as $p) {
            $map[(string) $p->stop_id] = (int) $p->id;
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    protected function importRoutes(string $dataDir): array
    {
        $path = $this->findFile($dataDir, 'routes.txt');
        if ($path === null) {
            throw new RuntimeException('routes.txt missing');
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('routes.txt unreadable');
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new RuntimeException('routes.txt empty');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');

        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false || empty($r['route_id'])) {
                continue;
            }
            $batch[] = [
                'route_id' => (string) $r['route_id'],
                'route_short_name' => (string) ($r['route_short_name'] ?? ''),
                'route_long_name' => $r['route_long_name'] ?? null,
                'route_type' => isset($r['route_type']) ? (int) $r['route_type'] : 3,
            ];
            if (count($batch) >= 800) {
                DB::table('gtfs_routes')->insert($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('gtfs_routes')->insert($batch);
        }

        $map = [];
        $pairs = DB::table('gtfs_routes')->select('id', 'route_id')->get();
        foreach ($pairs as $p) {
            $map[(string) $p->route_id] = (int) $p->id;
        }

        return $map;
    }

    protected function importCalendars(string $dataDir): void
    {
        $path = $this->findFile($dataDir, 'calendar.txt');
        if ($path === null) {
            return;
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return;
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');

        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false || empty($r['service_id'])) {
                continue;
            }
            $batch[] = [
                'service_id' => (string) $r['service_id'],
                'monday' => $this->dayChar($r['monday'] ?? '0'),
                'tuesday' => $this->dayChar($r['tuesday'] ?? '0'),
                'wednesday' => $this->dayChar($r['wednesday'] ?? '0'),
                'thursday' => $this->dayChar($r['thursday'] ?? '0'),
                'friday' => $this->dayChar($r['friday'] ?? '0'),
                'saturday' => $this->dayChar($r['saturday'] ?? '0'),
                'sunday' => $this->dayChar($r['sunday'] ?? '0'),
                'start_date' => $this->gtfsDateToSql($r['start_date'] ?? null),
                'end_date' => $this->gtfsDateToSql($r['end_date'] ?? null),
            ];
            if (count($batch) >= 800) {
                DB::table('gtfs_calendars')->insert($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('gtfs_calendars')->insert($batch);
        }
    }

    protected function importCalendarDates(string $dataDir): void
    {
        $path = $this->findFile($dataDir, 'calendar_dates.txt');
        if ($path === null) {
            return;
        }

        $calendarDateServiceIds = $this->scanCalendarDatesServiceIds($path);
        $this->ensureCalendarsForServices($calendarDateServiceIds);

        $handle = fopen($path, 'r');
        if (! $handle) {
            return;
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');

        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false) {
                continue;
            }
            $sid = $r['service_id'] ?? null;
            $batch[] = [
                'service_id' => $sid !== null && $sid !== '' ? (string) $sid : null,
                'date' => $this->gtfsDateToSql($r['date'] ?? null),
                'exception_type' => isset($r['exception_type']) ? (int) $r['exception_type'] : 1,
            ];
            if (count($batch) >= 800) {
                DB::table('gtfs_calendar_dates')->insert($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('gtfs_calendar_dates')->insert($batch);
        }
    }

    /**
     * @return list<string>
     */
    protected function scanCalendarDatesServiceIds(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return [];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
        $set = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false || empty($r['service_id'])) {
                continue;
            }
            $set[(string) $r['service_id']] = true;
        }
        fclose($handle);

        return array_keys($set);
    }

    protected function importShapeGroups(string $dataDir): void
    {
        $ids = [];

        $shapesPath = $this->findFile($dataDir, 'shapes.txt');
        if ($shapesPath !== null) {
            $handle = fopen($shapesPath, 'r');
            if ($handle) {
                $header = fgetcsv($handle);
                if ($header) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) < count($header)) {
                            continue;
                        }
                        $row = array_slice($row, 0, count($header));
                        $r = array_combine($header, $row);
                        if ($r !== false && ! empty($r['shape_id'])) {
                            $ids[(string) $r['shape_id']] = true;
                        }
                    }
                }
                fclose($handle);
            }
        }

        $tripsPath = $this->findFile($dataDir, 'trips.txt');
        if ($tripsPath !== null) {
            $handle = fopen($tripsPath, 'r');
            if ($handle) {
                $header = fgetcsv($handle);
                if ($header) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) < count($header)) {
                            continue;
                        }
                        $row = array_slice($row, 0, count($header));
                        $r = array_combine($header, $row);
                        if ($r !== false && ! empty($r['shape_id'])) {
                            $ids[(string) $r['shape_id']] = true;
                        }
                    }
                }
                fclose($handle);
            }
        }

        if ($ids === []) {
            return;
        }

        $batch = [];
        foreach (array_keys($ids) as $sid) {
            $batch[] = ['shape_id' => $sid];
            if (count($batch) >= 800) {
                DB::table('gtfs_shape_groups')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            DB::table('gtfs_shape_groups')->insertOrIgnore($batch);
        }
    }

    protected function importShapes(string $dataDir): void
    {
        $path = $this->findFile($dataDir, 'shapes.txt');
        if ($path === null) {
            return;
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return;
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return;
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');

        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false || empty($r['shape_id'])) {
                continue;
            }
            $seq = $r['shape_pt_sequence'] ?? null;
            $batch[] = [
                'shape_id' => (string) $r['shape_id'],
                'shape_pt_lat' => (float) ($r['shape_pt_lat'] ?? 0),
                'shape_pt_lon' => (float) ($r['shape_pt_lon'] ?? 0),
                'shape_pt_sequence' => $seq !== null && $seq !== '' ? (int) $seq : 0,
            ];
            if (count($batch) >= 1000) {
                DB::table('gtfs_shapes')->insert($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('gtfs_shapes')->insert($batch);
        }
    }

    /**
     * @param  array<string, int>  $routeMap
     * @return array<string, int>
     */
    protected function importTrips(string $dataDir, array $routeMap): array
    {
        $path = $this->findFile($dataDir, 'trips.txt');
        if ($path === null) {
            throw new RuntimeException('trips.txt missing');
        }

        $this->ensureCalendarsForServices($this->scanTripServiceIds($path));

        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('trips.txt unreadable');
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new RuntimeException('trips.txt empty');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');

        $tripIds = [];
        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false || empty($r['trip_id']) || empty($r['route_id']) || empty($r['service_id'])) {
                continue;
            }
            $rid = (string) $r['route_id'];
            if (! isset($routeMap[$rid])) {
                continue;
            }
            $shape = $r['shape_id'] ?? null;
            $shape = $shape !== null && $shape !== '' ? (string) $shape : null;
            $dir = $r['direction_id'] ?? null;
            $batch[] = [
                'trip_id' => (string) $r['trip_id'],
                'route_id' => $routeMap[$rid],
                'service_id' => (string) $r['service_id'],
                'shape_id' => $shape,
                'direction_id' => $dir !== null && $dir !== '' ? (int) $dir : null,
            ];
            $tripIds[(string) $r['trip_id']] = true;
            if (count($batch) >= 800) {
                DB::table('gtfs_trips')->insert($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('gtfs_trips')->insert($batch);
        }

        if ($tripIds === []) {
            throw new RuntimeException('trips.txt produced no rows');
        }

        $keys = array_keys($tripIds);
        $map = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            $part = DB::table('gtfs_trips')->whereIn('trip_id', $chunk)->pluck('id', 'trip_id');
            foreach ($part as $tid => $id) {
                $map[(string) $tid] = (int) $id;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    protected function scanTripServiceIds(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return [];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
        $set = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false || empty($r['service_id'])) {
                continue;
            }
            $set[(string) $r['service_id']] = true;
        }
        fclose($handle);

        return array_keys($set);
    }

    /**
     * @param  list<string>  $serviceIds
     */
    protected function ensureCalendarsForServices(array $serviceIds): void
    {
        if ($serviceIds === []) {
            return;
        }

        $existing = DB::table('gtfs_calendars')->whereIn('service_id', $serviceIds)->pluck('service_id');
        $have = [];
        foreach ($existing as $sid) {
            $have[(string) $sid] = true;
        }

        $batch = [];
        foreach ($serviceIds as $sid) {
            if (isset($have[$sid])) {
                continue;
            }
            $batch[] = [
                'service_id' => $sid,
                'monday' => '1',
                'tuesday' => '1',
                'wednesday' => '1',
                'thursday' => '1',
                'friday' => '1',
                'saturday' => '1',
                'sunday' => '1',
                'start_date' => '2000-01-01',
                'end_date' => '2099-12-31',
            ];
            if (count($batch) >= 500) {
                DB::table('gtfs_calendars')->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            DB::table('gtfs_calendars')->insert($batch);
        }
    }

    /**
     * @param  array<string, int>  $tripMap
     * @param  array<string, int>  $stopMap
     */
    protected function importStopTimes(string $dataDir, array $tripMap, array $stopMap): void
    {
        $path = $this->findFile($dataDir, 'stop_times.txt');
        if ($path === null) {
            throw new RuntimeException('stop_times.txt missing');
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('stop_times.txt unreadable');
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new RuntimeException('stop_times.txt empty');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');

        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }
            $row = array_slice($row, 0, count($header));
            $r = array_combine($header, $row);
            if ($r === false) {
                continue;
            }
            $tid = (string) ($r['trip_id'] ?? '');
            $sid = (string) ($r['stop_id'] ?? '');
            if ($tid === '' || $sid === '') {
                continue;
            }
            if (! isset($tripMap[$tid], $stopMap[$sid])) {
                continue;
            }
            $batch[] = [
                'trip_id' => $tripMap[$tid],
                'stop_id' => $stopMap[$sid],
                'arrival_time' => $this->normalizeGtfsTime($r['arrival_time'] ?? '00:00:00'),
                'departure_time' => $this->normalizeGtfsTime($r['departure_time'] ?? '00:00:00'),
                'stop_sequence' => isset($r['stop_sequence']) ? (int) $r['stop_sequence'] : 0,
            ];
            if (count($batch) >= 2000) {
                DB::table('gtfs_stop_times')->insert($batch);
                $batch = [];
            }
        }
        fclose($handle);
        if ($batch !== []) {
            DB::table('gtfs_stop_times')->insert($batch);
        }
    }

    protected function normalizeGtfsTime(mixed $value): string
    {
        $s = trim((string) $value);
        if ($s === '') {
            return '00:00:00';
        }

        return strlen($s) > 12 ? substr($s, 0, 12) : $s;
    }

    protected function dayChar(mixed $v): string
    {
        $s = (string) $v;

        return ($s === '1') ? '1' : '0';
    }

    protected function gtfsDateToSql(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = preg_replace('/\D/', '', (string) $v);
        if (strlen($s) !== 8) {
            return null;
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
