---
name: start-container
description: >-
  Starts Fraudebot backend Docker containers for the current Cursor Agent
  worktree when they are stopped or missing. Use when the user runs
  /start-container, asks to start the worktree container, turn the API back
  on, or needs php artisan test inside the worktree stack after containers
  were shut down. Also use when create-worktree finished without starting
  containers and the user now wants them running.
disable-model-invocation: true
icon: container
---

# Start worktree containers

Start the backend pair for the linked checkout the agent is working in. Do this when `fraudebot-backend-<slug>` or `fraudebot-web-<slug>` is not running, or when tests and artisan commands need the worktree PHP container.

Leave the main Compose project on port **9000**. MySQL and Redis stay `fraudebot-db-1` and `fraudebot-cache-1`.

## Know the checkout

The container slug is the current branch name after the last `/`. It must be kebab-case (`feature/support-tab` → `support-tab`).

If this worktree is not on a branch, ask for the branch name and check it out first (see [create-worktree](../create-worktree/SKILL.md)).

Refuse to run this skill for the main checkout (first path in `git worktree list`). That stack is the Compose project, not a linked worktree pair.

## Before starting

Check whether the containers already exist:

```bash
docker ps -a --format '{{.Names}} {{.Status}}' | grep -E 'fraudebot-(backend|web)-<slug>'
```

If both names exist and `docker ps` shows them **Up**, skip the start script. Run the health check below and report the API URL.

If the names exist but are **Exited**, start them instead of creating new ones:

```bash
docker start "fraudebot-backend-<slug>" "fraudebot-web-<slug>"
```

Then run the health check.

If the containers do not exist, run the repo script from the worktree root:

```bash
bash .cursor/skills/create-worktree/scripts/start-worktree.sh "$(pwd)"
```

Pass a host port as the second argument only when the user named one:

```bash
bash .cursor/skills/create-worktree/scripts/start-worktree.sh "$(pwd)" 9002
```

Omit the port when the user did not name one. The script publishes the next free port from 9001 upward.

Keep `start-worktree` in the command. A repo hook looks for that text and reattaches MySQL to the shared API network before the script runs.

The script copies the main worktree's Laravel `.env` when this checkout does not have one. `APP_URL` is the only value it changes. It refuses host ports **80**, **9000**, **3306**, and **6379**. It does not `docker compose up`, and it does not stop `fraudebot-web-1`, `fraudebot-backend-1`, `fraudebot-frontend-1`, `fraudebot-db-1`, or `fraudebot-cache-1`.

If the script reports that `fraudebot-backend-<slug>` or `fraudebot-web-<slug>` already exists, leave those containers as they are. Do not remove them and do not start a second pair.

Do not rebuild `docker run` by hand. Do not point Docker at `fraudebot/docker`. The Compose project lives in the sibling directory `fraudebot-docker`.

A named port that is already published is a stop. Tell the user which container holds it. Do not stop that container to take the port.

For a backend-only worktree, use the script above. Use `~/.cursor/skills/fraudebot-worktree-container/scripts/start-worktree.sh` only when the user also wants the frontend container, and pass it the checkout Cursor created.

## After they are up

The script prints `API: http://localhost:<port>` when it created new containers. When you only `docker start`ed, read the published port from `docker port fraudebot-web-<slug> 80`.

Some addresses on `fraudebot_fraudebot-api-network` accept ARP and then drop TCP, so PHP can resolve `db` and `cache` and still time out. The start script checks MySQL `3306` and Redis `6379` from inside the new PHP container when it creates one. When the first address fails, it moves that container to a free address from `172.24.0.10` upward and checks again.

If the containers already existed, the script does not perform that check. Request `GET http://localhost:<published-port>/api/public/healthcheck` with an 8 second limit. `{"status":"ok"}` means the checkout is ready. A timeout, or a 500 whose log says MySQL or Redis timed out, means the PHP container is on a dead address. Disconnect `fraudebot-backend-<slug>` from `fraudebot_fraudebot-api-network` and connect it again with an unused address from `172.24.0.10` upward, then retry the health check. Leave the nginx container and the main stack running.

Verify:

```bash
curl -fsS -m 8 "http://127.0.0.1:<port>/api/public/healthcheck"
```

Tell the user the API URL.

Use the worktree container, not `docker compose exec`:

```bash
docker exec -w /var/www/backend fraudebot-backend-<slug> php artisan test
```

If the health check still does not answer, follow `docker logs` for `fraudebot-backend-<slug>`.
