---
name: access-to-redis
description: >-
  Runs redis-cli against the Redis instance shared by a Fraudebot backend
  worktree (SET/GET, FLUSHALL, SCAN, etc.). Use when inspecting or changing
  cache data, debugging Laravel Redis keys, or when the user mentions Redis,
  FLUSHALL, or cache keys for the local Docker stack.
disable-model-invocation: true
icon: database
---

# Access Redis for a worktree backend

Worktree PHP containers and the main Compose backend all use the same Redis: Compose service `cache` (`fraudebot-cache-1`). Inside PHP, the host name is `cache` on port `6379`. Do not start a second Redis container for a worktree.

Run commands through the repo script. Do not hand-roll `docker exec` flags unless the script cannot reach Redis.

## Before you run anything destructive

`FLUSHALL` and `FLUSHDB` wipe data for **every** checkout and the main API. Say so out loud when the user asks for a full wipe. Prefer `--scan` and targeted `DEL` when debugging.

Laravel adds a key prefix (`REDIS_PREFIX` in `.env`, default derived from `APP_NAME` in `config/database.php`). Raw `GET foo` often misses cache entries; use `KEYS` / `--scan` with the prefix or read keys from application code.

Default connection uses logical DB `REDIS_DB` (often `0`). Laravel’s cache connection uses `REDIS_CACHE_DB` (often `1`). Pass `--db 1` when inspecting cache store keys.

## Script

From any backend checkout (main or linked worktree):

```bash
bash .cursor/skills/access-to-redis/scripts/redis-query.sh [--worktree DIR] [--db N] [--check-backend] -- COMMAND [ARGS...]
bash .cursor/skills/access-to-redis/scripts/redis-query.sh [--worktree DIR] info
```

`--worktree` defaults to the current directory. `--check-backend` verifies `fraudebot-backend-<branch-slug>` can open `cache:6379` when that container exists.

## Examples

```bash
bash .cursor/skills/access-to-redis/scripts/redis-query.sh -- PING

bash .cursor/skills/access-to-redis/scripts/redis-query.sh -- SET demo:agent-test "hello"
bash .cursor/skills/access-to-redis/scripts/redis-query.sh -- GET demo:agent-test
bash .cursor/skills/access-to-redis/scripts/redis-query.sh -- DEL demo:agent-test

bash .cursor/skills/access-to-redis/scripts/redis-query.sh -- FLUSHALL

bash .cursor/skills/access-to-redis/scripts/redis-query.sh -- --scan --pattern '*'

bash .cursor/skills/access-to-redis/scripts/redis-query.sh --db 1 KEYS '*'

bash .cursor/skills/access-to-redis/scripts/redis-query.sh --worktree . info
```

## When Redis commands fail

1. Run `info` and confirm Compose project paths (`FRAUDEBOT_DOCKER_DIR`, default `~/projects/fraudebot/fraudebot-docker`).
2. Ensure `fraudebot-cache-1` is running (`docker ps`).
3. For a linked worktree whose API container is up but PHP times out on Redis, follow the network steps in the **create-worktree** skill (bad address on `fraudebot_fraudebot-api-network`).

The VS Code task `[Cache] Clear Cache` is the same `FLUSHALL` against this Redis instance.
