#!/usr/bin/env bash

set -Eeuo pipefail

backend_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
frontend_dir=$(cd "${backend_dir}/../cabinetPsy" && pwd)

fail() {
    printf 'Development startup error: %s\n' "$1" >&2
    exit 1
}

port_is_in_use() {
    ss -ltn "( sport = :$1 )" | grep -q ":$1 "
}

command -v docker >/dev/null 2>&1 || fail 'Docker is required for Laravel Sail. Install and start Docker Desktop first.'
docker info >/dev/null 2>&1 || fail 'Docker is not reachable. Start Docker Desktop, then retry.'
command -v npm >/dev/null 2>&1 || fail 'npm is required to start the React SPA.'
command -v ss >/dev/null 2>&1 || fail 'The ss command is required to check local development ports.'

[[ -x "${backend_dir}/vendor/bin/sail" ]] || fail 'Laravel dependencies are missing. Run composer install in cabinetPsyAPI.'
[[ -f "${backend_dir}/.env" ]] || fail 'Backend .env is missing. Copy .env.example to .env and configure it.'
[[ -f "${frontend_dir}/package.json" ]] || fail "React project not found at ${frontend_dir}."
[[ -d "${frontend_dir}/node_modules" ]] || fail 'React dependencies are missing. Run npm install in ../cabinetPsy.'

cd "${backend_dir}"

if ./vendor/bin/sail ps --status running --quiet laravel.test | grep -q .; then
    printf 'Laravel Sail is already running at http://localhost:8000.\n'
elif port_is_in_use 8000; then
    fail 'Port 8000 is already in use. Stop the existing server before starting Sail.'
else
    printf 'Starting Laravel Sail at http://localhost:8000 ...\n'
    ./vendor/bin/sail up -d
fi

if port_is_in_use 5174; then
    fail 'Port 5174 is already in use. Stop the existing Vite server before using this launcher.'
fi

printf 'Starting React Vite at http://localhost:5174 ...\n'
printf 'Laravel logs: ./vendor/bin/sail logs -f laravel.test\n'
printf 'Health check: ./scripts/dev-health.sh\n\n'

cd "${frontend_dir}"
exec npm run dev -- --host 0.0.0.0 --port 5174 --strictPort
