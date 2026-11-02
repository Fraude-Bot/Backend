#!/usr/bin/env bash
# Stop backend containers for a Cursor Agent worktree.
# Usage: stop-worktree.sh [worktree-dir]
set -euo pipefail

if (($# > 1)); then
  echo "Usage: stop-worktree.sh [worktree-dir]" >&2
  exit 1
fi

worktree_dir="$(cd "${1:-.}" && pwd)"

if [[ ! -f "${worktree_dir}/artisan" ]]; then
  echo "Missing ${worktree_dir}/artisan. Run this from the backend worktree." >&2
  exit 1
fi

main_worktree="$(git -C "${worktree_dir}" worktree list --porcelain | awk '/^worktree / { print substr($0, 10); exit }')"
if [[ -z "${main_worktree}" || ! -d "${main_worktree}" ]]; then
  echo "Could not find the main worktree for ${worktree_dir}." >&2
  exit 1
fi

if [[ "${worktree_dir}" == "${main_worktree}" ]]; then
  echo "Refusing to stop Compose containers from the main worktree (${main_worktree})." >&2
  exit 1
fi

branch="$(git -C "${worktree_dir}" branch --show-current)"
if [[ -z "${branch}" ]]; then
  echo "This worktree is not on a branch." >&2
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

protected=(fraudebot-backend-1 fraudebot-web-1 fraudebot-frontend-1 fraudebot-db-1 fraudebot-cache-1)
for name in "${protected[@]}"; do
  if [[ "${backend_name}" == "${name}" || "${web_name}" == "${name}" ]]; then
    echo "Refusing to stop protected container ${name}." >&2
    exit 1
  fi
done

stopped_any=0
for name in "${web_name}" "${backend_name}"; do
  if ! docker container inspect "${name}" >/dev/null 2>&1; then
    continue
  fi
  running="$(docker inspect -f '{{.State.Running}}' "${name}" 2>/dev/null || echo false)"
  if [[ "${running}" == "true" ]]; then
    docker stop "${name}" >/dev/null
    echo "Stopped ${name}."
    stopped_any=1
  else
    echo "Container ${name} was not running."
  fi
  docker rm "${name}" >/dev/null
  echo "Removed ${name}."
  stopped_any=1
done

if ((stopped_any == 0)); then
  echo "No worktree containers ${web_name} or ${backend_name} to stop."
  exit 0
fi

if docker network inspect "${worktree_network}" >/dev/null 2>&1; then
  if docker network rm "${worktree_network}" >/dev/null 2>&1; then
    echo "Removed network ${worktree_network}."
  else
    echo "Left network ${worktree_network} in place (still in use)." >&2
  fi
fi

echo "Branch: ${branch}"
echo "Run start-container when you need this checkout's API stack again."
