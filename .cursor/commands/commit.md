# Commit and push

Follow **`.cursor/agents/commit.md`** in full for this worktree.

Treat the **entire user message** with this slash (including any text after `/commit`) as instructions and overrides—for example scoping paths, skipping push, or confirming sensitive files.

Prefer running as the **`commit`** subagent in the background when available so the main chat stays free; if subagent delegation is unavailable, execute that agent file yourself.

Do not open a pull request; use `/publish` for that.
