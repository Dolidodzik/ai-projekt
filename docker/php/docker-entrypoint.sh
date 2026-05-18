#!/bin/sh
set -e

cd /var/www/html

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

php artisan db:seed --force --no-ansi
php artisan storage:link --force --no-ansi 2>/dev/null || true

exec "$@"
