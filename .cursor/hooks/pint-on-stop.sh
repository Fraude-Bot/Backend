#!/usr/bin/env bash
# Format this checkout with Pint in fix mode after an agent or subagent stops,
# only when that run edited at least one PHP file.
set -uo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd -P)"
root_real="$(realpath -m "$root")"

finish() {
  printf '%s\n' '{}'
  exit 0
}

if ! command -v python3 >/dev/null 2>&1; then
  echo "Pint hook: python3 is not on PATH." >&2
  cat >/dev/null
  finish
fi

parsed="$(python3 -c '
import json, sys
data = json.load(sys.stdin)
event = str(data.get("hook_event_name") or "")
conv = str(data.get("conversation_id") or "")
gen = str(data.get("generation_id") or "")
paths = []
file_path = data.get("file_path")
if isinstance(file_path, str):
    paths.append(file_path)
modified = data.get("modified_files")
if isinstance(modified, list):
    paths.extend(str(path) for path in modified)
php = any(path.lower().endswith(".php") for path in paths)
print(event)
print(conv)
print(gen)
print("1" if php else "0")
')"

event="$(sed -n '1p' <<<"$parsed")"
conversation_id="$(sed -n '2p' <<<"$parsed")"
generation_id="$(sed -n '3p' <<<"$parsed")"
php_in_payload="$(sed -n '4p' <<<"$parsed")"

safe() {
  printf '%s' "$1" | tr -c 'A-Za-z0-9._-' '_'
}

state_dir="/tmp/fraudebot-pint-edits/$(safe "$root_real")/$(safe "$conversation_id")/$(safe "$generation_id")"
marker="${state_dir}/php"

if [[ "$event" == "afterFileEdit" ]]; then
  if [[ "$php_in_payload" == "1" && -n "$conversation_id" && -n "$generation_id" ]]; then
    mkdir -p "$state_dir"
    : >"$marker"
  fi
  finish
fi

if [[ "$event" != "stop" && "$event" != "subagentStop" ]]; then
  finish
fi

edited_php=0
if [[ "$php_in_payload" == "1" ]]; then
  edited_php=1
fi
if [[ -f "$marker" ]]; then
  edited_php=1
fi

if [[ "$edited_php" != "1" ]]; then
  echo "Pint hook: no PHP file was edited." >&2
  finish
fi

rm -f "$marker"

if ! command -v docker >/dev/null 2>&1; then
  echo "Pint hook: docker is not on PATH." >&2
  finish
fi

container=""
workdir=""

while IFS= read -r name; do
  [[ "$name" == fraudebot-backend-* ]] || continue
  while IFS=$'\t' read -r source destination; do
    [[ -n "$source" && -n "$destination" ]] || continue
    source_real="$(realpath -m "$source")"
    if [[ "$source_real" == "$root_real" ]]; then
      container="$name"
      workdir="$destination"
      break 2
    fi
  done < <(docker inspect --format '{{range .Mounts}}{{.Source}}{{"\t"}}{{.Destination}}{{"\n"}}{{end}}' "$name" 2>/dev/null || true)
done < <(docker ps --format '{{.Names}}')

if [[ -z "$container" || -z "$workdir" ]]; then
  echo "Pint hook: no running fraudebot-backend container mounts ${root}." >&2
  finish
fi

lock="/tmp/fraudebot-pint-$(printf '%s' "$root_real" | md5sum | awk '{print $1}').lock"
exec 9>"$lock"
if command -v flock >/dev/null 2>&1; then
  flock 9
fi

echo "Pint hook: docker exec -w ${workdir} ${container} vendor/bin/pint" >&2
if ! docker exec -w "$workdir" "$container" vendor/bin/pint >&2; then
  echo "Pint hook: Pint exited with an error in ${container}." >&2
fi

finish
