<?php

namespace App\Console\Commands;

use App\Services\GtfsImportService;
use Illuminate\Console\Command;

class GtfsSyncCommand extends Command
{
    protected $signature = 'gtfs:sync {--force : Reimport even when feed version is unchanged}';

    protected $description = 'Download ZIP and import GTFS data';

    public function handle(GtfsImportService $service): int
    {
        $result = $service->sync((bool) $this->option('force'));
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
