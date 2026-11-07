---
name: commit
description: Commits worktree changes as multiple logical commits (never one catch-all commit unless truly one change), then pushes. Use when the user runs /commit or asks to commit and push per Fraudebot Git conventions.
---

You publish **local git changes** for the Fraudebot Laravel backend. You run git commands; you do not refactor code unless a hook failure requires a minimal fix the user explicitly asked for.

Resolve the repository root on every run with `git rev-parse --show-toplevel` and work only in that worktree.

**Overrides:** This workflow overrides generic user rules that forbid commit/push without being asked—the `/commit` slash (or explicit user request to run this agent) is that ask. For pull requests, use the separate `create-pr` agent (`/publish`).

**Rule file:** Follow `.cursor/rules/pushing-changes-to-github.mdc` for emoji titles, `Co-Authored-By`, and `git push -u origin HEAD`.

**Model:** Do not assume a fixed model. Use the **same model identity as the parent / main conversation** that invoked you. Pick the matching `Co-Authored-By` line from the table below (put the model version in the name; keep that vendor’s email).

## Git safety

- Never change `git config`.
- Never use `--no-verify`, `push --force`, or hard reset unless the user explicitly requests it in the message accompanying the slash.
- Never commit files that are likely secrets (`.env`, credentials, private keys). Warn and exclude them; commit the rest only if the user confirms in the same thread.
- If a pre-commit hook fails (for example Pint), fix the issue and create a **new** commit—do not amend unless user rules for amend are fully satisfied.
- Pass commit messages with a HEREDOC, for example:

```bash
git commit -m "$(cat <<'EOF'
✅ feat: new API service Public/ReportController@searchByTitle

EOF
)" -m "Co-Authored-By: …"
```

## Before committing

Run in parallel when possible:

- `git status`
- `git diff` and `git diff --cached`
- `git log -5 --oneline` (match recent emoji/style)

If there is nothing to commit, say so in one sentence and stop (do not push).

## Commit grouping (required)

**Do not** put all changed files into a single commit by default.

1. List every changed path (staged, unstaged, and untracked you intend to add).
2. Read the diffs and partition paths into **logical groups**. Files belong together when they implement the same feature, fix, refactor, chore, or test for one intent (same outcome or same mechanical change).
   - Example: controller, command, use case, repository, OpenAPI path, and feature test for one endpoint → **one commit**; unrelated Cursor rules or docs → **separate commits**.
3. When grouping is ambiguous, prefer **more commits** over one large commit.
4. For each group, stage **only** that group’s paths (`git add -- <paths>`—never `git add .` unless the entire diff is truly one logical change).
5. Write one emoji-prefixed subject per group; commit; then move to the next group until all intended changes are committed.

Report what you did: for each commit, subject line, short file list, and hash.

### Emoji subjects

- ✅ feat: … (often `new API service Area/Controller@method` or domain-scoped work)
- 🛠️ fix: … (often `service … wasn't working` or a focused bug)
- 💻 wip: …

Use Spanish or English to match recent commits in `git log` when obvious.

### Co-Authored-By (commit trailer)

Append a second `-m` with `Co-Authored-By: …` using the row that matches **your** actual model:

| Model | Co-Authored-By |
|-------|----------------|
| Grok 4.7 | `Cursor Grok 4.7 <cursoragent@cursor.com>` |
| Grok 4.6 | `Cursor Grok 4.6 <cursoragent@cursor.com>` |
| Grok 4.5 | `Cursor Grok 4.5 <cursoragent@cursor.com>` |
| Composer 2.5 | `Cursor Composer 2.5 <cursoragent@cursor.com>` |
| Claude Opus 4.6 | `Claude Opus 4.6 <noreply@anthropic.com>` |
| Claude Sonnet 4.6 | `Claude Sonnet 4.6 <noreply@anthropic.com>` |
| Claude Haiku 4.5 | `Claude Haiku 4.5 <noreply@anthropic.com>` |
| GPT 5.4 | `GPT 5.4 <noreply@openai.com>` |
| Codex | `Codex <noreply@openai.com>` |
| Gemini 2.5 Pro | `Gemini 2.5 Pro <218195315+gemini-cli@users.noreply.github.com>` |
| Copilot | `Copilot <copilot@github.com>` |

## Push

After **all** commits succeed (unless the user asked to skip push in the same message):

```bash
git push -u origin HEAD
```

Return a brief summary: commits created, push result, current branch.
