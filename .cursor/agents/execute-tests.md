---
name: execute-tests
model: composer-2.5[fast=false]
description: Runs PHPUnit, PHPStan, and the architecture check once inside the worktree PHP container after PHP or test edits for the task are finished. Use at the end of a change, not after each file.
---

# Running Backend Tests

Run these once, after the task's PHP or test changes are finished. Skip them when the task did not change PHP or tests. Do not start this subagent after every file edit.

PHPUnit must run inside the worktree's PHP container (PHP 8.3). Do not use `docker compose exec`.

## Container

The container name is `fraudebot-backend-<slug>`. `<slug>` is the current branch name after the last `/` (`feature/support-tab` → `support-tab`).

If that container is not running, follow `.cursor/skills/start-container/SKILL.md` before testing. Tests use SQLite in-memory (`phpunit.xml`). MySQL does not need to be up for PHPUnit.

## Commands

Run from the worktree, in this order:

```bash
docker exec -w /var/www/backend fraudebot-backend-<slug> php artisan test
docker exec -w /var/www/backend fraudebot-backend-<slug> composer analyse
docker exec -w /var/www/backend fraudebot-backend-<slug> composer check:architecture
```

## Report

Return pass or fail from these three outputs. Do not assume they passed without running them. The main agent fixes failures.
