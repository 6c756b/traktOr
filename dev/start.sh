#!/usr/bin/env bash
# Starts the local TraktOr dev environment: PHP backend (port 8000) + Vite frontend (port 5173).
# Local development only -- see deploy/deploy.sh for deployment.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"

if [ ! -f "$BACKEND_DIR/config/config.php" ]; then
  echo "Missing: backend/config/config.php" >&2
  echo "Copy backend/config/config.example.php there and fill in local values." >&2
  exit 1
fi

if [ ! -d "$FRONTEND_DIR/node_modules" ]; then
  echo "frontend/node_modules is missing -- run 'npm install' once in the frontend/ folder." >&2
  exit 1
fi

if lsof -i :8000 >/dev/null 2>&1; then
  echo "Port 8000 is already in use -- is the backend already running?" >&2
  exit 1
fi

cleanup() {
  echo ""
  echo "Stopping backend..."
  kill "$BACKEND_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo "Starting PHP backend on http://localhost:8000 ..."
(cd "$BACKEND_DIR" && php -S localhost:8000 index.php) &
BACKEND_PID=$!

echo -n "Waiting for backend..."
for _ in $(seq 1 40); do
  if curl -sf http://localhost:8000/api/ping >/dev/null 2>&1; then
    echo " ready."
    break
  fi
  echo -n "."
  sleep 0.25
done

echo "Starting Vite frontend on http://localhost:5173 ..."
echo "(Ctrl+C stops both servers)"
cd "$FRONTEND_DIR" && npm run dev
