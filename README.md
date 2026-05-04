# Command Center

Workspace and session dashboard for multi-project development with Claude Code.
Laravel 13 + React 19 + Tailwind 4 + shadcn-style components on top of SQLite.

## What it does

- Auto-discovers every Claude Code config tree under `$HOME` (`.claude`, `.claude-savvior`, etc.) and treats each one as an **Account**.
- Walks each account's `projects/<encoded-cwd>/<guid>.jsonl` files and turns them into **Sessions** in the dashboard, tagged with the account they came from -- so you can tell at a glance which session belongs to your **personal** vs **savvior** Claude account.
- Lets you organize **Workspaces** under named **Projects**.
- Provides a `php artisan cc:register` command consumer projects can call from any workspace to label their current session.

## Install

```bash
composer create-project bookbreakdown/commandcenter commandcenter
cd commandcenter
cp .env.example .env
# edit .env -- set COMMANDCENTER_HOME and COMMANDCENTER_HOME_ROOT
php artisan key:generate
php artisan migrate --force
npm install --no-audit --no-fund
npm run build
```

Then point an Apache vhost at `public/` and add `commandcenter.test` (or whatever you call it) to your hosts file.

## Configure

Two PC-specific values in `.env`:

| Key | Purpose |
|---|---|
| `COMMANDCENTER_HOME` | Absolute path of this checkout. Consumer projects use it in their universal self-registration prompt. |
| `COMMANDCENTER_HOME_ROOT` | Parent dir holding `.claude/`, `.claude-savvior/`, etc. Apache on Windows often runs as `LocalSystem` and cannot resolve `$HOME`/`$USERPROFILE`, so pin the user-profile dir explicitly. Leave blank on POSIX boxes where the service inherits `$HOME`. |

## First run

```bash
php artisan cc:project:add TMO
php artisan cc:workspace:add TMO 'C:\wamp\www\tmo-tools3'
php artisan cc:discover
```

`cc:discover` scans for accounts and rebuilds the sessions table; safe to re-run any time.

## Universal self-registration prompt

Paste this into any project's `CLAUDE.md` so agents working there register themselves:

```markdown
## Session Tracking

Register this session with Command Center. Pick the form that matches your shell:

- bash / zsh: `( cd "$COMMANDCENTER_HOME" && php artisan cc:register --label "PROJECTNAME | brief task description" )`
- PowerShell: `& php "$env:COMMANDCENTER_HOME\artisan" cc:register --label "PROJECTNAME | brief task description"`

Replace PROJECTNAME with this project's name. The description should be 3-5 words.
GUID, account, and workspace are auto-detected from the working directory.
```

The registrar's auto-detection looks at every account's `projects/<encoded-cwd>/` dir for the most recently-written `<guid>.jsonl`, so it Just Works whether you started Claude under `claude` or `savvior-claude`.

## Architecture

```
app/
  Console/Commands/
    DiscoverCommand.php       cc:discover
    ProjectAddCommand.php     cc:project:add
    WorkspaceAddCommand.php   cc:workspace:add
    RegisterCommand.php       cc:register
  Http/Controllers/
    DashboardController.php   GET /api/dashboard, PATCH /api/sessions/{guid}
  Models/
    Account.php Project.php Workspace.php Session.php
  Services/
    WorkspacePathEncoder.php       /var/www/x -> -var-www-x; C:\x -> C--x
    ClaudeAccountDiscovery.php     finds .claude* dirs, upserts accounts
    ClaudeSessionDiscovery.php     walks each account's projects/ tree
    DashboardService.php           composes the API tree
resources/
  js/
    app.tsx                   React entry
    pages/Dashboard.tsx       polls /api/dashboard every 5s
    components/
      ProjectCard.tsx WorkspaceTable.tsx SessionRow.tsx
      ui/{badge,button,card}.tsx   shadcn-style primitives
  views/app.blade.php         shell that mounts <div id="app">
routes/
  web.php                     all unmatched URLs render the React shell
  api.php                     /api/dashboard, /api/sessions/{guid}
database/migrations/
  2026_05_04_*                accounts, projects, workspaces, claude_sessions
tests/
  Unit/WorkspacePathEncoderTest.php
  Feature/AccountDiscoveryTest.php
  Feature/SessionDiscoveryTest.php
  Feature/DashboardEndpointTest.php
```

## Development

```bash
composer dev   # concurrent: artisan serve + vite dev + queue + pail
# or:
php artisan serve
npm run dev
```

Run tests:

```bash
php artisan test
```
