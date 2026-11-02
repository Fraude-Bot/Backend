---
name: create-worktree
description: >-
  Creates a Fraudebot backend Git worktree when this chat is still on the main
  checkout and moves this session onto that worktree. Use when the user runs
  /create-worktree or asks for a new backend worktree branch. Does not start
  Docker; use the start-container skill when the checkout needs the API stack.
disable-model-invocation: true
icon: git-branch
---

# Cursor agent worktrees

When the user runs `/create-worktree`, get the branch name, make sure a backend worktree is on that branch, and move this session from the main checkout onto that worktree. Do this before editing application code.

The worktree button sometimes opens the agent already inside a new checkout. `/create-worktree` typed in the main repo does not. There is no hidden checkout to find. Create one and move this session onto it from this same chat.

This skill does not start the API containers. When the worktree has no `fraudebot-backend-<slug>` / `fraudebot-web-<slug>` pair running, follow the **start-container** skill after the move.

Leave the main Compose project running on port **9000**. MySQL and Redis stay `fraudebot-db-1` and `fraudebot-cache-1`.

## Ask for the branch

Ask for the full branch name (`feature/support-tab`, `fix/payment-methods`) and wait for the answer.

Skip it only when the user already gave it in this conversation. Use the branch name as given. Do not add a `feature/` prefix they did not use, and do not invent a name from the task.

The worktree directory slug is the branch name after the last `/`. It must be kebab-case. If it is not, ask again.

Rename this chat to the branch name, exactly as given (`feature/support-tab`).

Do not ask for host ports here. Ports belong to **start-container**.

## Make sure the worktree exists

`git worktree list` is the inventory. One call is enough. Do not search the home directory or `~/.cursor/worktrees` for a checkout that might have been created. A missing checkout means create one.

The main checkout is `/home/cedano/projects/fraudebot/fraudebot-backend`. Never check the feature branch out there. That moves the user's main working tree off `main`, and the start script refuses to launch a second stack for that path.

**Already inside a worktree.** When `git rev-parse --show-toplevel` is not the main checkout, that directory is the one to use. From there:

```bash
git checkout -b "<branch>"
```

When that branch already exists, use `git checkout "<branch>"` instead. If Git reports the branch is already checked out somewhere else, stop and tell the user which path has it. Leave any branch name Cursor already generated; after switching, that name can stay in the branch list.

When the user asked for a dedicated worktree path (a new slug under Cursor's worktrees folder), create a new worktree on the branch instead of reusing the current checkout. Follow the **Still on the main checkout** steps below.

**Still on the main checkout.** This is the usual `/create-worktree` case. Another worktree on a different branch belongs to another chat. Do not reuse it, and do not add the feature branch to it.

If `git worktree list` already shows the requested branch, use that path. Git allows a branch in only one checkout.

Otherwise create the worktree outside the repository, beside Cursor's other backend worktrees. The directory name is the slug, so the path matches the container names `fraudebot-backend-<slug>` and `fraudebot-web-<slug>`:

```bash
mkdir -p /home/cedano/.cursor/worktrees/fraudebot-backend__WSL__Ubuntu_
git worktree add -b "<branch>" \
  "/home/cedano/.cursor/worktrees/fraudebot-backend__WSL__Ubuntu_/<slug>" \
  HEAD
```

When the branch already exists and is not checked out, drop `-b` and pass the branch name instead of `HEAD`:

```bash
git worktree add \
  "/home/cedano/.cursor/worktrees/fraudebot-backend__WSL__Ubuntu_/<slug>" \
  "<branch>"
```

If that directory already exists and is not a worktree, stop and tell the user. Do not delete it.

Do not create a `worktrees/` directory inside the repository. Confirm the new checkout:

```bash
git -C "<worktree>" rev-parse --show-toplevel
git -C "<worktree>" branch --show-current
```

Stay in this conversation. Do not ask the user to switch sessions, chats, or windows, and do not tell them to press the worktree button.

## Move this session onto the worktree

When `git rev-parse --show-toplevel` is already the target worktree path, skip this section.

Git worktrees from `git worktree add` are not `cursorfs-clone` siblings. **Do not** call `move_agent_to_cloned_root` for them. That tool only accepts a verbatim clone on the **same branch as this chat's recorded branch** (typically under `~/.cursor/cursorfs-clone/`). A new feature worktree on a different branch aborts with a generic tool error.

For a git worktree path, call `move_agent_to_root` with the worktree's absolute path. Cursor may run `git fetch origin <branch>` as part of that move.

**Branch already on origin.** The move should succeed. Confirm the session itself is on the worktree:

```bash
git rev-parse --show-toplevel
git branch --show-current
```

Both commands must report the new worktree path and the requested branch.

**Branch local-only** (not on `origin` yet). `move_agent_to_root` may fail because the remote branch does not exist. Do not fall back to `move_agent_to_cloned_root`. Tell the user to open the worktree folder in Cursor (for example **File → Open Folder** on the path below), then continue in this chat. Until the workspace root changes, run Git and file edits with `git -C "<worktree>"` or absolute paths under `<worktree>`. Optionally they can `git push -u origin "<branch>"` and retry `move_agent_to_root`.

Do not keep editing application code from the main checkout or from another worktree once the target checkout exists.

**Example paths.** Slug `new-skill-to-start-container`, branch `feature/new-skill-to-start-container`:

`/home/cedano/.cursor/worktrees/fraudebot-backend__WSL__Ubuntu_/new-skill-to-start-container`

**Example.** The user says `/create-worktree branch feature/new-skill-to-start-container` while this chat is on the main checkout. Create that worktree, call `move_agent_to_root` with that path (or ask them to open the folder if the branch is still local-only), confirm this session is on `feature/new-skill-to-start-container`, then run **start-container** if they need the API stack.

## Container

Do not start Docker from this skill. When the user wants the API stack or no container is running for this slug, read and follow `.cursor/skills/start-container/SKILL.md`.
