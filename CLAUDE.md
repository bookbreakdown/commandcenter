# Command Center

Workspace and session dashboard for multi-project development with Claude Code.

## Setup

1. Copy `database/seed.example.php` to `database/seed.php` and add your projects/workspaces
2. Run `php database/seed.php` to initialize the SQLite database
3. Set up an Apache/Nginx vhost pointing to `public/`
4. Add your `.test` domain to your hosts file

## Architecture

- `public/index.php` -- Dashboard UI (polls API every 5s)
- `public/api.php` -- JSON API for CRUD operations
- `src/Database.php` -- SQLite data layer with git info discovery
- `bin/cc-register` -- CLI tool for agents to register/label sessions
- `database/schema.sql` -- Database schema

## Session Registration

From any workspace, agents can self-register:

```bash
/path/to/commandcenter/bin/cc-register --workspace /var/www/myproject --guid <SESSION_GUID> --label "PROJECT | task description"
```

GUID and workspace auto-detect from cwd when omitted:

```bash
/path/to/commandcenter/bin/cc-register --label "PROJECT | task description"
```

The dashboard provides one-click copy buttons for resume and label commands.

## Universal Self-Registration Prompt

Paste this block into any project's CLAUDE.md to enable self-registration. Replace the path and project name:

```markdown
## Session Tracking

When you start working, register this session with Command Center. Do not read or investigate the command center project — just run this command exactly:

    /var/www/commandcenter/bin/cc-register --label "PROJECTNAME | brief task description"

Replace PROJECTNAME with this project's name. The description should be 3-5 words summarizing what we are working on. GUID and workspace are auto-detected from your working directory.
```

## Local Overrides

- `CLAUDE.local.md` -- PC-specific project instructions (gitignored)
- `database/seed.php` -- Your project/workspace layout (gitignored)
- `database/*.db` -- Your session data (gitignored)
- `knowledge/` -- Your ops procedures (gitignored)
