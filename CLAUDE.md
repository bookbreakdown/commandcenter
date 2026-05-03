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

The dashboard provides one-click copy buttons for resume and label commands.

## Local Overrides

- `CLAUDE.local.md` -- PC-specific project instructions (gitignored)
- `database/seed.php` -- Your project/workspace layout (gitignored)
- `database/*.db` -- Your session data (gitignored)
- `knowledge/` -- Your ops procedures (gitignored)
