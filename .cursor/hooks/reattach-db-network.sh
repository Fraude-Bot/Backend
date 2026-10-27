#!/usr/bin/env bash
# Reattach MySQL before the create-worktree skill starts a checkout.
# Bridged traffic to fraudebot-db-1 can be dropped until its endpoint is created again.
set -uo pipefail

input="$(cat)"

command_text="$(printf '%s' "$input" | python3 -c '
import json, sys
try:
    data = json.load(sys.stdin)
except json.JSONDecodeError:
    data = {}
command = data.get("command")
print(command if isinstance(command, str) else "")
')"

allow() {
  printf '%s\n' '{ "permission": "allow" }'
  exit 0
}

if [[ "$command_text" != *start-worktree* ]]; then
  allow
fi

docker network disconnect fraudebot_fraudebot-api-network fraudebot-db-1 || true
docker network connect --alias db fraudebot_fraudebot-api-network fraudebot-db-1 || true

allow
