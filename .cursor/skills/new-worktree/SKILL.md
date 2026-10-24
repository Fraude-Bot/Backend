---
name: new-worktree
description: >-
  Asks for the new branch name, then starts Fraudebot backend Docker containers
  for a Cursor Agent worktree using the main worktree's environment. Use when
  Cursor creates a Git worktree with the worktree button, when an Agent session
  starts in a new worktree, or when the user runs /worktree.
disable-model-invocation: true
---

# Cursor agent worktrees

When Cursor creates a Git worktree for an Agent, ask for the new branch name, then start the backend containers for that checkout. Do this before editing application code.

The worktree button, the Agents Window, and `/worktree` create the checkout. This skill starts its containers. Leave the main Compose project running on port 9000.

## Ask for the new branch name

Ask for the new branch name and wait for the answer. Skip the question only when the user already gave the full branch name in this conversation (`feature/support-tab`, `fix/payment-methods`). Use that name as given. Do not add a `feature/` prefix they did not use, and do not invent a name from the task.

The container slug is the branch name after the last `/`. It must be kebab-case. If it is not, ask again.

## Use the checkout Cursor created

Cursor creates the Git worktree and chooses its directory. Leave that path alone. Do not run `git worktree add`, and do not create a `worktrees/` directory inside the repository.

From the checkout Cursor opened (`git rev-parse --show-toplevel`):

```bash
git checkout -b "<branch>"
```

When that branch already exists, use `git checkout "<branch>"` instead. If Git reports the branch is already checked out somewhere else, stop and tell the user. Leave any branch Cursor generated in place.

The main checkout is `/home/cedano/projects/fraudebot/backend`. When `git rev-parse --show-toplevel` is that path, stay in this conversation. Pass the worktree path Cursor created as the first argument to the start script. Do not ask the user to switch sessions, chats, or windows.

## Start the containers

Execute this script. Do not rebuild the `docker run` flags by hand.

```bash
bash .cursor/skills/new-worktree/scripts/start-worktree.sh
```

Run it with the worktree as the current directory, or pass that directory as the first argument. The script reads the current branch.

It copies the main worktree's Laravel `.env` when this checkout does not have one, and it loads `docker/php/.env.php`, the same env file as the main `backend` service. `APP_URL` is the only value it changes, so this checkout is published on its own host port. MySQL and Redis stay the main `db` and `cache` containers.

The script refuses host ports **80**, **9000**, **3306**, and **6379**. It does not `docker compose up`, and it does not stop `fraudebot-web-1`, `fraudebot-backend-1`, `fraudebot-frontend-1`, `fraudebot-db-1`, or `fraudebot-cache-1`.

If the script reports that `fraudebot-backend-<slug>` or `fraudebot-web-<slug>` already exists, leave those containers as they are.

For a backend-only worktree, use the script above. Use `~/.cursor/skills/fraudebot-worktree-container/scripts/start-worktree.sh` only when the user also wants the frontend container, and pass it the checkout Cursor created.

## After they are up

Use the worktree container, not `docker compose exec`:

```bash
docker exec -w /var/www/backend fraudebot-backend-<slug> php artisan test
```

Tell the user the URL printed by the script. Composer install finishes before that line. If the URL does not answer yet, follow `docker logs` for `fraudebot-backend-<slug>`.
