#!/usr/bin/env bash
# Start backend containers for a Cursor Agent worktree.
# Uses the main worktree's Laravel .env and docker/php/.env.php.
# Usage: start-worktree.sh [worktree-dir]
set -euo pipefail

ROOT="${FRAUDEBOT_ROOT:-/home/cedano/projects/fraudebot}"
DOCKER_DIR="${ROOT}/docker"
MAIN_API_NETWORK="fraudebot_fraudebot-api-network"

port_forbidden() {
  case "$1" in
    80|9000|3306|6379) return 0 ;;
    *) return 1 ;;
  esac
}

port_in_use() {
  local port="$1"
  if port_forbidden "${port}"; then
    return 0
  fi
  if docker ps --format '{{.Ports}}' | grep -Eq "(^|[^0-9])${port}->"; then
    return 0
  fi
  if command -v ss >/dev/null 2>&1 && ss -ltn | awk '{print $4}' | grep -Eq ":${port}$"; then
    return 0
  fi
  return 1
}

next_free() {
  local port="$1"
  while port_in_use "${port}"; do
    if ((port >= 65535)); then
      echo "No free port at or above ${1}." >&2
      exit 1
    fi
    port=$((port + 1))
  done
  printf '%s\n' "${port}"
}

require_absent() {
  local name="$1"
  if docker container inspect "${name}" >/dev/null 2>&1; then
    echo "Container ${name} already exists. Leave it running, or remove it only if the user asked to recreate it." >&2
    exit 1
  fi
}

if (($# > 1)); then
  echo "Usage: start-worktree.sh [worktree-dir]" >&2
  exit 1
fi

worktree_dir="$(cd "${1:-.}" && pwd)"

if [[ ! -f "${worktree_dir}/artisan" ]]; then
  echo "Missing ${worktree_dir}/artisan. Run this from the backend worktree Cursor created." >&2
  exit 1
fi

main_worktree="$(git -C "${worktree_dir}" worktree list --porcelain | awk '/^worktree / { print substr($0, 10); exit }')"
if [[ -z "${main_worktree}" || ! -d "${main_worktree}" ]]; then
  echo "Could not find the main worktree for ${worktree_dir}." >&2
  exit 1
fi

if [[ "${worktree_dir}" == "${main_worktree}" ]]; then
  echo "Refusing to start a second stack for the main worktree (${main_worktree})." >&2
  exit 1
fi

branch="$(git -C "${worktree_dir}" branch --show-current)"
if [[ -z "${branch}" ]]; then
  echo "This worktree is not on a branch. Ask for the new branch name and check it out first." >&2
  exit 1
fi

slug="${branch##*/}"
if [[ ! "${slug}" =~ ^[a-z0-9][a-z0-9-]*$ ]]; then
  echo "Branch '${branch}' must end in a kebab-case name (for example feature/support-tab)." >&2
  exit 1
fi

backend_name="fraudebot-backend-${slug}"
web_name="fraudebot-web-${slug}"
worktree_network="fraudebot-worktree-${slug}"

require_absent "${backend_name}"
require_absent "${web_name}"

backend_port="$(next_free 9001)"

if [[ ! -f "${DOCKER_DIR}/php/.env.php" ]]; then
  echo "Missing ${DOCKER_DIR}/php/.env.php. The main backend service loads this file." >&2
  exit 1
fi

if [[ ! -f "${worktree_dir}/.env" ]]; then
  if [[ ! -f "${main_worktree}/.env" ]]; then
    echo "Main worktree has no .env at ${main_worktree}/.env." >&2
    exit 1
  fi
  cp "${main_worktree}/.env" "${worktree_dir}/.env"
  echo "Copied .env from ${main_worktree}/.env"
fi

# shellcheck source=/dev/null
source "${DOCKER_DIR}/scripts/common.sh"
prepare_backend "${worktree_dir}"

if grep -q '^APP_URL=' "${worktree_dir}/.env"; then
  sed -i "s|^APP_URL=.*|APP_URL=http://localhost:${backend_port}|" "${worktree_dir}/.env"
else
  printf '\nAPP_URL=http://localhost:%s\n' "${backend_port}" >> "${worktree_dir}/.env"
fi

docker network inspect "${worktree_network}" >/dev/null 2>&1 || docker network create "${worktree_network}"

docker run --detach \
  --name "${backend_name}" \
  --user "$(id -u):$(id -g)" \
  --network "${worktree_network}" \
  --network-alias backend \
  -e HOME=/tmp \
  -e COMPOSER_HOME=/tmp/composer \
  -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
  -e APP_URL="http://localhost:${backend_port}" \
  --env-file "${DOCKER_DIR}/php/.env.php" \
  -v "${worktree_dir}:/var/www/backend" \
  -v "${DOCKER_DIR}/php/conf.d/php.ini:/usr/local/etc/php/conf.d/php.ini:ro" \
  -v "${DOCKER_DIR}/php/conf.d/opcache.ini:/usr/local/etc/php/conf.d/opcache.ini:ro" \
  -w /var/www/backend \
  laravel-clean-app

docker network connect "${MAIN_API_NETWORK}" "${backend_name}"

docker run --detach \
  --name "${web_name}" \
  --network "${worktree_network}" \
  --network-alias web \
  -p "${backend_port}:80" \
  -v "${worktree_dir}:/var/www/backend:ro" \
  -v "${DOCKER_DIR}/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro" \
  nginx:alpine

echo "Running composer install in ${backend_name}..."
docker exec -w /var/www/backend "${backend_name}" \
  composer install --no-interaction --prefer-dist
docker exec -w /var/www/backend "${backend_name}" \
  composer dump-autoload

echo "API: http://localhost:${backend_port}"
echo "Branch: ${branch}"
echo "Env file: ${DOCKER_DIR}/php/.env.php (same as the main backend service)"
echo "Laravel .env: copied from ${main_worktree}/.env; APP_URL is http://localhost:${backend_port}"

if docker container inspect fraudebot-web-1 >/dev/null 2>&1; then
  if ! docker port fraudebot-web-1 80 | grep -q ':9000$'; then
    echo "Main API container fraudebot-web-1 is no longer published on port 9000." >&2
    exit 1
  fi
fi
