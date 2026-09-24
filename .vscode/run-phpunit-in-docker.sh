#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
CONTAINER_ROOT="/var/www/backend"
COMPOSE_FILE="${BACKEND_ROOT}/../../docker/docker-compose.yml"

args=()
for arg in "$@"; do
  mapped="${arg//${BACKEND_ROOT}/${CONTAINER_ROOT}}"

  if [[ "${mapped}" == *"/vendor/bin/phpunit"* ]]; then
    mapped="./vendor/bin/phpunit"
  fi

  if [[ "${mapped}" == /usr/bin/php* ]] || [[ "${mapped}" == /usr/local/bin/php* ]]; then
    continue
  fi

  args+=("${mapped}")
done

exec docker compose -f "${COMPOSE_FILE}" exec -T -w "${CONTAINER_ROOT}" backend php "${args[@]}"
