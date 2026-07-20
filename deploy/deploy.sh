#!/usr/bin/env bash
set -euo pipefail

# Builds the frontend, stages frontend + backend into dist-ftp/ and uploads it
# via lftp, if available and deploy/.env is configured.
# config.php is deliberately NOT synced -- it's only uploaded once,
# manually, on the server.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAGE_DIR="$ROOT_DIR/dist-ftp"

# Must be loaded before the build -- BASE_PATH controls the Vite build (subfolder hosting).
if [ -f "$ROOT_DIR/deploy/.env" ]; then
  # shellcheck disable=SC1091
  source "$ROOT_DIR/deploy/.env"
fi

echo "Der Traktor rollt: Frontend wird gebaut..."
(cd "$ROOT_DIR/frontend" && VITE_BASE_PATH="${BASE_PATH:-}" npm run build)

echo "Staging-Verzeichnis wird zusammengestellt..."
rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR/api"

cp -R "$ROOT_DIR/frontend/dist/." "$STAGE_DIR/"
cp "$ROOT_DIR/VERSION" "$STAGE_DIR/VERSION"

cp "$ROOT_DIR/backend/index.php" "$STAGE_DIR/api/index.php"
cp "$ROOT_DIR/backend/bootstrap.php" "$STAGE_DIR/api/bootstrap.php"
cp "$ROOT_DIR/backend/cron.php" "$STAGE_DIR/api/cron.php"
cp "$ROOT_DIR/backend/.htaccess" "$STAGE_DIR/api/.htaccess"
cp -R "$ROOT_DIR/backend/src" "$STAGE_DIR/api/src"
mkdir -p "$STAGE_DIR/api/config"
cp "$ROOT_DIR/backend/config/.htaccess" "$STAGE_DIR/api/config/.htaccess"
find "$STAGE_DIR" -name '.DS_Store' -delete

echo "Staging fertig: $STAGE_DIR"

if ! command -v lftp >/dev/null 2>&1; then
  echo "lftp nicht installiert -- manueller Upload von $STAGE_DIR per FTP-Client noetig."
  echo "(brew install lftp fuer automatischen Upload)"
  exit 0
fi

if [ -z "${FTP_HOST:-}" ] || [ -z "${FTP_USER:-}" ] || [ -z "${FTP_PASS:-}" ] || [ -z "${FTP_REMOTE_DIR:-}" ]; then
  echo "deploy/.env fehlt oder unvollstaendig -- manueller Upload von $STAGE_DIR per FTP-Client noetig."
  echo "(siehe deploy/.env.example)"
  exit 0
fi

echo "Upload nach $FTP_HOST:$FTP_REMOTE_DIR..."
lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "mirror -R --delete --exclude api/config/config.php $STAGE_DIR $FTP_REMOTE_DIR; bye"

echo "Fertig. Die Ernte ist eingefahren."
