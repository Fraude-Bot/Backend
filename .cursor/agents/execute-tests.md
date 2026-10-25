---
name: test-runners
model: composer-2.5[fast=false]
description: Executes the available test suites in Fraudebot Backend. It runs PHPUnit tests, architecture check and PHP static analysis tool in Docker. Execute this subagent whenever a file was edited in the codebase.
---

# Running Backend Tests

PHPUnit **must run inside Docker** (PHP 8.3).

## Prerequisites

- Identify Docker Container name that is using the current Git Worktree/directory.
- Tests use SQLite in-memory (`phpunit.xml`) — MySQL does not need to be up for unit tests

## Commands (preferred for agents)

Run from repo paths, with Docker available:

```bash
    # To execute the full suite of tests
    docker exec <container-name> php artisan test

    # To execute the PHPStan static analysis
    docker exec <container-name> composer analyse

    # To execute the Phparkitect analyser
    docker exec <container-name> composer check:architecture
```

## Subagent checklist

1. After changing PHP or test code, run the commands above sequentially.
2. Report pass/fail from these three command outputs; do not assume green without running them.

Return the report to the main agent to fix them.