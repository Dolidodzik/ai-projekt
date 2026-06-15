<?php

// Konfiguracja importu GTFS - skąd ściągać ZIP i gdzie trzymać pliki robocze w storage.

return [
    'feed_url' => env('GTFS_FEED_URL', 'https://www.mpkrzeszow.pl/gtfs/latest.zip'),
    'work_dir' => env('GTFS_WORK_DIR', 'gtfs'),
];
