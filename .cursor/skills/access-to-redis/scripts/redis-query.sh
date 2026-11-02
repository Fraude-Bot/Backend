#!/usr/bin/env bash
# Run redis-cli against the Redis instance shared by Fraudebot backend worktrees.
# Usage: redis-query.sh [--worktree DIR] [--db N] [--check-backend] -- COMMAND [ARGS...]
#        redis-query.sh [--worktree DIR] info
set -euo pipefail

ROOT="${FRAUDEBOT_ROOT:-/home/cedano/projects/fraudebot}"
DOCKER_DIR="${FRAUDEBOT_DOCKER_DIR:-${ROOT}/fraudebot-docker}"
COMPOSE_FILE="${DOCKER_DIR}/docker-compose.yml"
CACHE_CONTAINER="${FRAUDEBOT_REDIS_CONTAINER:-fraudebot-cache-1}"

usage() {
  cat <<'EOF'
Usage:
  redis-query.sh [--worktree DIR] [--db N] [--check-backend] -- COMMAND [ARGS...]
  redis-query.sh [--worktree DIR] info

Runs redis-cli in the main Compose Redis service (fraudebot-cache-1). Every
worktree backend container reaches this same instance as host "cache".

Examples:
  redis-query.sh -- PING
  redis-query.sh -- SET demo:key "hello"
  redis-query.sh -- GET demo:key
  redis-query.sh -- FLUSHALL
  redis-query.sh -- --scan --pattern '*search*'
  redis-query.sh --worktree . --db 1 KEYS '*'

Options:
  --worktree DIR   Backend checkout (default: current directory). Used for
                   branch context, .env Redis settings, and optional backend check.
  --db N           Select Redis logical database before COMMAND (redis-cli -n).
  --check-backend  When the worktree's fraudebot-backend-<slug> container exists,
                   verify it can open cache:6379 before running redis-cli.
  info             Print worktree Redis-related .env values and compose target.

Environment:
  FRAUDEBOT_ROOT, FRAUDEBOT_DOCKER_DIR, FRAUDEBOT_REDIS_CONTAINER
EOF
}

die() {
  echo "$*" >&2
  exit 1
}

resolve_worktree() {
  local dir="${1:-.}"
  if [[ ! -f "${dir}/artisan" ]]; then
    die "Missing ${dir}/artisan. Pass --worktree with a Fraudebot backend checkout."
  fi
  cd "${dir}" && pwd
}

backend_name_for_worktree() {
  local worktree="$1"
  local branch slug
  branch="$(git -C "${worktree}" branch --show-current 2>/dev/null || true)"
  if [[ -z "${branch}" ]]; then
    return 1
  fi
  slug="${branch##*/}"
  if [[ ! "${slug}" =~ ^[a-z0-9][a-z0-9-]*$ ]]; then
    return 1
  fi
  printf 'fraudebot-backend-%s\n' "${slug}"
}

main_worktree_dir() {
  git -C "${1}" worktree list --porcelain | awk '/^worktree / { print substr($0, 10); exit }'
}

env_value() {
  local file="$1"
  local key="$2"
  if [[ ! -f "${file}" ]]; then
    return 1
  fi
  grep -E "^${key}=" "${file}" | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

backend_reaches_cache() {
  local backend_name="$1"
  docker exec "${backend_name}" php -r '
    $socket = @fsockopen("cache", 6379, $errno, $errstr, 3);
    if ($socket === false) {
      fwrite(STDERR, "cache:6379 $errstr\n");
      exit(1);
    }
    fclose($socket);
  '
}

run_redis_cli() {
  local db_flag=()
  if [[ -n "${REDIS_DB_SELECT:-}" ]]; then
    db_flag=(-n "${REDIS_DB_SELECT}")
  fi

  if [[ -f "${COMPOSE_FILE}" ]]; then
    docker compose -f "${COMPOSE_FILE}" exec -T -w / cache redis-cli "${db_flag[@]}" "$@"
    return
  fi

  if docker container inspect "${CACHE_CONTAINER}" >/dev/null 2>&1; then
    docker exec -T "${CACHE_CONTAINER}" redis-cli "${db_flag[@]}" "$@"
    return
  fi

  die "Redis is not reachable. Expected ${COMPOSE_FILE} or container ${CACHE_CONTAINER}."
}

print_info() {
  local worktree="$1"
  local env_file="${worktree}/.env"
  local main
  main="$(main_worktree_dir "${worktree}")"

  echo "Worktree: ${worktree}"
  echo "Branch: $(git -C "${worktree}" branch --show-current 2>/dev/null || echo '(detached)')"
  echo "Compose Redis service: cache (${COMPOSE_FILE})"
  echo "Container name: ${CACHE_CONTAINER}"
  if [[ -f "${env_file}" ]]; then
    echo "REDIS_HOST (Laravel .env): $(env_value "${env_file}" REDIS_HOST || echo '(unset)')"
    echo "REDIS_PORT: $(env_value "${env_file}" REDIS_PORT || echo '(unset)')"
    echo "REDIS_DB: $(env_value "${env_file}" REDIS_DB || echo '(unset)')"
    echo "REDIS_CACHE_DB: $(env_value "${env_file}" REDIS_CACHE_DB || echo '(unset)')"
    echo "REDIS_PREFIX: $(env_value "${env_file}" REDIS_PREFIX || echo '(unset, see config/database.php)')"
    echo "CACHE_PREFIX: $(env_value "${env_file}" CACHE_PREFIX || echo '(unset)')"
  else
    echo "Laravel .env: missing (copy from ${main}/.env before running the API)."
  fi

  local backend_name
  if backend_name="$(backend_name_for_worktree "${worktree}")"; then
    echo "Worktree backend container: ${backend_name}"
    if docker container inspect "${backend_name}" >/dev/null 2>&1; then
      if backend_reaches_cache "${backend_name}"; then
        echo "Backend → cache:6379: ok"
      else
        echo "Backend → cache:6379: failed (see create-worktree skill for network fix)"
      fi
    else
      echo "Backend container: not running (start with create-worktree skill if needed)"
    fi
  fi
}

worktree_dir="."
REDIS_DB_SELECT=""
check_backend=0
mode="cli"

while (($# > 0)); do
  case "$1" in
    -h | --help)
      usage
      exit 0
      ;;
    --worktree)
      shift
      [[ $# -gt 0 ]] || die "--worktree requires a directory"
      worktree_dir="$1"
      shift
      ;;
    --db)
      shift
      [[ $# -gt 0 ]] || die "--db requires a number"
      REDIS_DB_SELECT="$1"
      shift
      ;;
    --check-backend)
      check_backend=1
      shift
      ;;
    info)
      mode="info"
      shift
      ;;
    --)
      shift
      break
      ;;
    *)
      if [[ "${mode}" == "info" ]]; then
        die "Unexpected argument after info: $1"
      fi
      break
      ;;
  esac
done

worktree_dir="$(resolve_worktree "${worktree_dir}")"

if [[ "${mode}" == "info" ]]; then
  print_info "${worktree_dir}"
  exit 0
fi

if ((check_backend == 1)); then
  backend_name=""
  if backend_name="$(backend_name_for_worktree "${worktree_dir}")"; then
    if docker container inspect "${backend_name}" >/dev/null 2>&1; then
      backend_reaches_cache "${backend_name}" || die "Backend ${backend_name} cannot reach Redis."
    fi
  fi
fi

if (($# == 0)); then
  usage
  exit 1
fi

run_redis_cli "$@"
