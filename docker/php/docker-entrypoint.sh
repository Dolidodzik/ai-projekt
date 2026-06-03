#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
fi

seed_needed() {
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (! Illuminate\Support\Facades\Schema::hasTable("migrations")) {
    exit(0);
}
$count = (int) Illuminate\Support\Facades\DB::table("migrations")->count();
exit($count === 0 ? 0 : 1);
'
}

gtfs_needed() {
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (! Illuminate\Support\Facades\Schema::hasTable("gtfs_stops")) {
    exit(0);
}
$count = (int) Illuminate\Support\Facades\DB::table("gtfs_stops")->count();
exit($count === 0 ? 0 : 1);
'
}

users_needed() {
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (! Illuminate\Support\Facades\Schema::hasTable("users")) {
    exit(0);
}
$count = (int) Illuminate\Support\Facades\DB::table("users")->count();
exit($count === 0 ? 0 : 1);
'
}

max_attempts=30
attempt=0

if seed_needed; then
  echo "Fresh database — running migrations with seed..."
  until php artisan migrate --seed --force --no-ansi; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge "$max_attempts" ]; then
      echo "Database migration/seed failed after ${max_attempts} attempts."
      exit 1
    fi
    echo "Waiting for database (${attempt}/${max_attempts})..."
    sleep 2
  done
else
  until php artisan migrate --force --no-ansi; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge "$max_attempts" ]; then
      echo "Database migration failed after ${max_attempts} attempts."
      exit 1
    fi
    echo "Waiting for database (${attempt}/${max_attempts})..."
    sleep 2
  done

  if users_needed; then
    echo "No users in database — running seeders..."
    php artisan db:seed --force --no-ansi
  elif gtfs_needed; then
    echo "GTFS tables empty — syncing feed..."
    php artisan gtfs:sync --force --no-ansi || true
  fi
fi

php artisan storage:link --force --no-ansi 2>/dev/null || true

exec "$@"
