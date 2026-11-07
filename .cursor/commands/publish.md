# Create pull request

Follow **`.cursor/agents/create-pr.md`** in full for this worktree.

Treat the **entire user message** with this slash (including any text after `/publish`) as instructions—especially the **target base branch** (default `main` if unspecified).

Prefer running as the **`create-pr`** subagent in the background when available; if subagent delegation is unavailable, execute that agent file yourself.

Do not create new commits or push unless the user explicitly asked in the same message; use `/commit` for that.
