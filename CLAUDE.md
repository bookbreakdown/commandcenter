# Command Center -- Claude Code Notes

Laravel 13 + React 19 dashboard that surfaces Claude Code sessions across one or more Claude accounts.

## Architecture rules

- **Service layer first.** All business logic lives in `app/Services/*`; controllers/commands are thin shells that delegate. Tests live in `tests/Feature/` and `tests/Unit/` and must pass before shipping.
- **No auth.** Localhost dev tool, single user. Don't add middleware that requires it.
- **Cross-platform.** Anything touching disk paths must work for both `/var/www/...` and `C:\wamp\www\...`. Use `WorkspacePathEncoder` and `DIRECTORY_SEPARATOR` rather than hardcoding slashes.
- **Apache may run as a different user.** Read user-profile paths from `.env` (`COMMANDCENTER_HOME_ROOT`), never from `$HOME`/`$USERPROFILE` alone -- those are blank when Apache runs as `LocalSystem` on Windows. The `ClaudeAccountDiscovery::homeRoot()` helper has the right precedence.
- **Tests use raw env vars** (`putenv`) which Laravel's `env()` does not see. Services that read env values must use `getenv()` / `$_ENV` / `$_SERVER` directly (see the `rawEnv()` helper in `ClaudeAccountDiscovery`).

## Useful commands

```bash
php artisan cc:project:add NAME
php artisan cc:workspace:add PROJECT 'absolute/path'
php artisan cc:discover                    # refresh accounts + sessions
php artisan cc:register --label "..."      # mark current session active+labeled
php artisan test                           # full PHPUnit suite
npm run build                              # production bundle (public/build)
npm run dev                                # vite dev server with HMR
composer dev                               # everything at once (vite + serve + queue + pail)
```

## Where Claude session data lives

```
$COMMANDCENTER_HOME_ROOT/
  .claude/                       # personal Max account (default)
    projects/
      C--wamp-www-tmo-tools3/    # Windows path encoding
        <guid>.jsonl
        <guid>.jsonl
  .claude-savvior/               # named savvior account
    projects/
      ...
```

The encoded directory name is produced by `WorkspacePathEncoder::encode()` -- every `/`, `\`, and `:` becomes a `-`.

## Adding a new feature

1. Write a service in `app/Services/` (pure, dependency-injected).
2. Cover it with a feature test that uses a fake `$HOME` under `sys_get_temp_dir()` (see `AccountDiscoveryTest::setUp`).
3. Wire an artisan command and/or API route on top.
4. Add UI components under `resources/js/components/` and consume the existing `lib/api.ts` helpers.
5. `php artisan test && npm run build`.

## Things to avoid

- Don't add a `users` table or auth middleware -- this is single-user.
- Don't import Bootstrap, jQuery, or any non-Tailwind styling.
- Don't move business logic into Eloquent models or controllers -- keep them thin.
- Don't fork the encoded-path format from what Claude Code uses on disk; keep `WorkspacePathEncoder` as the single source of truth.
