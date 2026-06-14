#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
fi

max_attempts=30
attempt=0

until php artisan migrate --force --no-ansi; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge "$max_attempts" ]; then
    echo "Database migration failed after ${max_attempts} attempts."
    exit 1
  fi
  echo "Waiting for database (${attempt}/${max_attempts})..."
  sleep 2
done

echo "Sprawdzanie aktualnosci danych GTFS..."
if ! php artisan gtfs:sync --no-ansi; then
  echo "GTFS sync nie powiodl sie — uzywam danych z backupu."
fi

php artisan storage:link --force --no-ansi 2>/dev/null || true

exec "$@"
