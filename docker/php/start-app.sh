#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "composer install (first run / fresh clone)..."
  composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
fi

until php artisan migrate --force; do
  sleep 2
done

echo "Sprawdzanie aktualnosci danych GTFS..."
php artisan gtfs:sync || echo "GTFS sync nie powiodl sie — uzywam danych z backupu."

exec php-fpm -F
