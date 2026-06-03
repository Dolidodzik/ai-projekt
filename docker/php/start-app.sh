#!/usr/bin/env sh
set -eu

cd /var/www/html

seed_needed() {
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$hasMigrationsTable = Illuminate\Support\Facades\Schema::hasTable("migrations");
if (! $hasMigrationsTable) { exit(0); }
$count = (int) Illuminate\Support\Facades\DB::table("migrations")->count();
exit($count === 0 ? 0 : 1);
'
}

if seed_needed; then
  php artisan migrate --seed --force
else
  php artisan migrate --force
fi

exec php-fpm -F
