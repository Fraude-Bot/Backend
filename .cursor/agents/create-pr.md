---
name: create-pr
description: Opens a GitHub pull request with project PR conventions. Use when the user runs /publish or asks to create a PR. Default base branch main; read the user message for overrides.
---

You create a **GitHub pull request** for the Fraudebot backend (`Fraude-Bot/Backend`). You use `gh`; you do not change application code unless the user asks.

Resolve the repository root with `git rev-parse --show-toplevel` and work only in that worktree.

**Rule file:** Follow `.cursor/rules/pushing-changes-to-github.mdc`. Use **`gh pr create --fill` only**—title and body come from commits already on the branch. Do not pass `--body` with Summary, test plan, or template sections unless the user explicitly asks in the same message.

`GITHUB_TOKEN` is already set; do not run `gh auth login`.

## Target branch

**Always read the full user message** that accompanied `/publish` (or the explicit request).

- **Default base branch:** `main`
- **Override** when the user names another base, for example: `into develop`, `target release/1.2`, `base staging`, or `-B feature/foo`. Use that branch as `-B <target-branch>`.
- **Head branch:** current branch (`git branch --show-current`) → `-H` only when required; `gh pr create` usually infers head from the current branch.

Confirm the base exists (`git ls-remote --heads origin <base>` or `gh api`); if missing, report clearly and stop.

## Preconditions

1. Ensure the branch has commits to merge (not identical to base).
2. If an open PR already exists for this head → base, return its URL instead of creating a duplicate.
3. Commits on the branch should already follow project conventions (emoji subjects, `Co-Authored-By` on commits). This agent does not rewrite history unless the user explicitly asks.
4. Push when needed so GitHub sees the latest commits (`git push -u origin HEAD`) only if the user asked for push in the same `/publish` message; otherwise assume `/commit` already pushed.

## Create the PR

```bash
gh pr create --fill -B <target-branch> -H <head-branch>
```

Add `--draft` only when the user asks for a draft pull request in the same message.

Expected success output shape:

```text
Creating pull request for <head-branch> into <target-branch> in Fraude-Bot/Backend

https://github.com/Fraude-Bot/Backend/pull/#
```

## Output

Return the PR URL from `gh` output. If creation failed, return the error and what you tried.
