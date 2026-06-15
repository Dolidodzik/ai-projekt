#!/usr/bin/env bash
# Generuje świeży dump bazy z działającego kontenera db -> backend/database/backups/ai2_projekt.dump
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/backend/database/backups"
BACKUP_FILE="${BACKUP_DIR}/ai2_projekt.dump"

cd "${ROOT_DIR}"

echo "==> Tworzenie backupu bazy..."
mkdir -p "${BACKUP_DIR}"
docker compose exec -T db pg_dump \
  -U "${POSTGRES_USER:-ai2_user}" \
  -Fc \
  --no-owner \
  --role="${POSTGRES_USER:-ai2_user}" \
  "${POSTGRES_DB:-ai2_projekt}" > "${BACKUP_FILE}"

echo "==> Backup zapisany: ${BACKUP_FILE}"
ls -lh "${BACKUP_FILE}"
