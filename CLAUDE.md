# Command Center

Workspace and session dashboard for multi-project development with Claude Code.

## Setup

1. Copy `database/seed.example.php` to `database/seed.php` and add your projects/workspaces
2. Run `php database/seed.php` to initialize the SQLite database
3. Set up an Apache/Nginx vhost pointing to `public/`
4. Add your `.test` domain to your hosts file
5. Set the `COMMANDCENTER_HOME` environment variable to the absolute path of this repo so consumer projects can locate `bin/cc-register` without hardcoding the path. Examples:
   - **Linux / macOS** (in `~/.bashrc` or `~/.zshrc`):
     ```bash
     export COMMANDCENTER_HOME=/var/www/commandcenter
     ```
   - **Windows (PowerShell, `$PROFILE`)**:
     ```powershell
     $env:COMMANDCENTER_HOME = 'C:\wamp\www\dev-tools\commandcenter'
     ```
   - Or set it as a system environment variable so every shell sees it.

   Copy `.env.example` to `.env` to keep a record of the value alongside the repo (gitignored).

## Architecture

- `public/index.php` -- Dashboard UI (polls API every 5s)
- `public/api.php` -- JSON API for CRUD operations
- `src/Database.php` -- SQLite data layer with git info discovery
- `bin/cc-register` -- CLI tool for agents to register/label sessions
- `database/schema.sql` -- Database schema

## Session Registration

From any workspace, agents can self-register. The binary lives at `$COMMANDCENTER_HOME/bin/cc-register`, so the path is portable across dev boxes once the env var is set (see Setup).

```bash
"$COMMANDCENTER_HOME/bin/cc-register" --workspace /var/www/myproject --guid <SESSION_GUID> --label "PROJECT | task description"
```

GUID and workspace auto-detect from cwd when omitted:

```bash
"$COMMANDCENTER_HOME/bin/cc-register" --label "PROJECT | task description"
```

The dashboard provides one-click copy buttons for resume and label commands.

## Universal Self-Registration Prompt

Paste this block into any project's CLAUDE.md to enable self-registration. The path resolves via `$COMMANDCENTER_HOME` (set per dev box during Setup), so the same block works on every machine — just replace the project name.

```markdown
## Session Tracking

When you start working, register this session with Command Center. Do not read or investigate the command center project — just run the cc-register binary at the path defined by the `COMMANDCENTER_HOME` environment variable on this machine. Pick the form that matches your shell:

- bash / zsh:    `"$COMMANDCENTER_HOME/bin/cc-register" --label "PROJECTNAME | brief task description"`
- PowerShell:    `& "$env:COMMANDCENTER_HOME\bin\cc-register" --label "PROJECTNAME | brief task description"`

If `COMMANDCENTER_HOME` is not set, ask the user where commandcenter lives on this PC instead of guessing. Replace PROJECTNAME with this project's name. The description should be 3-5 words summarizing what we are working on. GUID and workspace are auto-detected from your working directory.
```

## Local Overrides

- `CLAUDE.local.md` -- PC-specific project instructions (gitignored)
- `database/seed.php` -- Your project/workspace layout (gitignored)
- `database/*.db` -- Your session data (gitignored)
- `knowledge/` -- Your ops procedures (gitignored)
