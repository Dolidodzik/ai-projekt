#!/bin/bash
set -euo pipefail

if [ -f /backups/ai2_projekt.dump ]; then
  echo "Przywracanie bazy danych z backupu..."
  pg_restore \
    --username="${POSTGRES_USER}" \
    --dbname="${POSTGRES_DB}" \
    --no-owner \
    --role="${POSTGRES_USER}" \
    --verbose \
    /backups/ai2_projekt.dump
  echo "Backup przywrocony pomyslnie."
else
  echo "Brak pliku /backups/ai2_projekt.dump — pomijam restore."
fi
