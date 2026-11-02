---
name: stop-container
description: >-
  Stops and removes the Fraudebot backend Docker containers for the current
  Git worktree (fraudebot-backend-<slug> and fraudebot-web-<slug>). Use when
  the user runs /stop-container, asks to turn off the worktree API stack, or
  shut down the containers started by start-container for this checkout.
disable-model-invocation: true
icon: container
---

# Worktree backend containers

Stop the PHP and nginx pair **start-container** created for the worktree this session is on.

Leave the main Compose project running on port **9000**. Do **not** stop `fraudebot-web-1`, `fraudebot-backend-1`, `fraudebot-frontend-1`, `fraudebot-db-1`, or `fraudebot-cache-1`.

## When to run

Run from the same backend worktree that started the containers, not from the main checkout at `/home/cedano/projects/fraudebot/fraudebot-backend`.

Confirm the session checkout:

```bash
git rev-parse --show-toplevel
git branch --show-current
```

Derive the slug from the branch name after the last `/`. It must be kebab-case. If it is not, stop and ask for a proper branch name.

The container names are `fraudebot-backend-<slug>` and `fraudebot-web-<slug>`. When `docker container inspect` reports neither exists, tell the user nothing is running for this branch and stop.

## Stop the containers

Execute this script. Do not run ad hoc `docker stop` on Compose service names.

```bash
bash .cursor/skills/stop-container/scripts/stop-worktree.sh [worktree-dir]
```

Run it from the worktree root. When omitted, `[worktree-dir]` defaults to the current directory. The script reads the branch of that checkout and refuses the main worktree.

It stops and removes `fraudebot-web-<slug>` and `fraudebot-backend-<slug>`, then removes the dedicated network `fraudebot-worktree-<slug>` when nothing else uses it.

It does not remove the checkout's Laravel `.env` or change `APP_URL`. **start-container** reuses them on the next start.

If the user also started a frontend worktree container through `~/.cursor/skills/fraudebot-worktree-container/`, that container is separate. Stop it only when they ask; this skill targets the backend API pair.

## After they stop

Confirm both containers are gone:

```bash
docker container inspect "fraudebot-backend-<slug>"
docker container inspect "fraudebot-web-<slug>"
```

Both commands should fail. Tell the user the worktree API stack is off and they can run **start-container** again when they need the API.
