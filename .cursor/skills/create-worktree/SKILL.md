---
name: create-worktree
description: >-
  Asks for the new branch name, then starts Fraudebot backend Docker containers
  for a Cursor Agent worktree on their own host port. Use when the user runs
  /create-worktree or /worktree, when Cursor opens a Git worktree, or when an
  Agent session starts in a new backend checkout and needs its API container.
  Also use it when the user names the host port for that checkout, such as
  "use port 9002".
disable-model-invocation: true
icon: git-branch
---

# Cursor agent worktrees

When Cursor creates a Git worktree for an Agent, ask for the new branch name, then start the backend containers for that checkout. Do this before editing application code.

The worktree button, the Agents Window, and `/worktree` create the checkout. `/create-worktree` starts its containers. Leave the main Compose project running on port 9000. MySQL and Redis stay the main `fraudebot-db-1` and `fraudebot-cache-1` containers. A second copy of them will not see the same data, and this checkout is mounted into the containers that already share their Docker network.

## Ask for the new branch name

Ask for the new branch name and wait for the answer. Skip the question only when the user already gave the full branch name in this conversation (`feature/support-tab`, `fix/payment-methods`). Use that name as given. Do not add a `feature/` prefix they did not use, and do not invent a name from the task.

The container slug is the branch name after the last `/`. It must be kebab-case. If it is not, ask again.

Rename this chat to the branch name, exactly as given (`feature/support-tab`).

When the user names a host port, keep that number for the start command. Do not choose a nearby free port instead.

## Use the checkout

The first path in `git worktree list` is the main checkout. Do not hardcode it. Do not create a `worktrees/` directory inside the repository.

If a linked worktree is already on the named branch, use that directory. If Git reports the branch is already checked out somewhere else, stop and tell the user. Leave any branch Cursor generated in place.

When the current directory is a linked worktree and it is not on that branch yet:

```bash
git checkout -b "<branch>"
```

When that branch already exists, use `git checkout "<branch>"` instead.

When the current directory is the main checkout and no linked worktree has the branch, create the checkout beside the other Cursor worktrees for this repo. Reuse the parent directory those entries already use under `~/.cursor/worktrees/`. The slug is the directory name:

```bash
git worktree add -b "<branch>" "<parent>/<slug>"
```

Then move this agent to that directory before editing application code. A local-only branch is not on the remote yet. If that move fails while fetching the branch, stay in this conversation and pass the new directory to the start script. Do not ask the user to switch sessions.

## Start the containers

Run the repo script. Do not rebuild the `docker run` flags by hand, and do not point Docker at `fraudebot/docker`. The Compose project lives in the sibling directory `fraudebot-docker`. The script already mounts that directory's PHP env file, `php.ini`, and nginx config. It no longer sources `scripts/common.sh`; that file is not in the Docker project.

Pass the worktree path, and pass the host port only when the user named one:

```bash
bash .cursor/skills/create-worktree/scripts/start-worktree.sh "<worktree>" 9002
```

Omit the port argument when the user did not name one. The script then publishes the next free port from 9001 upward.

Keep `start-worktree` in the command. A repo hook looks for that text and reattaches MySQL to the shared API network before the script runs. Reattaching it yourself as well races the hook.

The script copies the main worktree's Laravel `.env` when this checkout does not have one. `APP_URL` is the only value it changes. It refuses host ports **80**, **9000**, **3306**, and **6379**. It does not `docker compose up`, and it does not stop `fraudebot-web-1`, `fraudebot-backend-1`, `fraudebot-frontend-1`, `fraudebot-db-1`, or `fraudebot-cache-1`.

A named port that is already published is a stop. Tell the user which container holds it. Do not stop that container to take the port.

If the script reports that `fraudebot-backend-<slug>` or `fraudebot-web-<slug>` already exists, leave those containers as they are. Do not remove them and do not start a second pair.

For a backend-only worktree, use the script above. Use `~/.cursor/skills/fraudebot-worktree-container/scripts/start-worktree.sh` only when the user also wants the frontend container, and pass it the checkout Cursor created.

## After they are up

The script prints `API: http://localhost:<port>` after Composer install and a health check. Tell the user that URL.

Some addresses on `fraudebot_fraudebot-api-network` accept ARP and then drop TCP, so PHP can resolve `db` and `cache` and still time out. The script checks MySQL `3306` and Redis `6379` from inside the new PHP container. When the first address fails, it moves that container to a free address from `172.24.0.10` upward and checks again. A fresh container on one network can reach the databases while this checkout's container cannot; that difference is the bad address, not a reason to recreate MySQL or Redis.

If the containers already existed, the script does not perform that check. Request `GET http://localhost:<published-port>/api/public/healthcheck` with an 8 second limit. `{"status":"ok"}` means the checkout is ready. A timeout, or a 500 whose log says MySQL or Redis timed out, means the PHP container is on a dead address. Disconnect `fraudebot-backend-<slug>` from `fraudebot_fraudebot-api-network` and connect it again with an unused address from `172.24.0.10` upward, then retry the health check. Leave the nginx container and the main stack running.

Use the worktree container, not `docker compose exec`:

```bash
docker exec -w /var/www/backend fraudebot-backend-<slug> php artisan test
```

If the health check still does not answer, follow `docker logs` for `fraudebot-backend-<slug>`.
