<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Project;
use App\Models\Session;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Composes the data tree that the dashboard renders: every Project, with its
 * Workspaces, with each Workspace's Sessions tagged by Account. Pure read
 * service -- discovery happens elsewhere; this just assembles what's already
 * in the DB.
 */
class DashboardService
{
    public function tree(): array
    {
        $accounts = Account::all()->keyBy('id');

        $projects = Project::with(['workspaces' => function ($q) {
            $q->orderBy('path');
        }])->orderBy('name')->get();

        $sessions = Session::with('account')
            ->whereNotNull('workspace_id')
            ->orderByDesc('jsonl_mtime')
            ->get()
            ->groupBy('workspace_id');

        return $projects->map(function (Project $project) use ($sessions, $accounts) {
            return [
                'id'   => $project->id,
                'name' => $project->name,
                'workspaces' => $project->workspaces->map(function (Workspace $ws) use ($sessions, $accounts) {
                    $wsSessions = $sessions->get($ws->id, collect());
                    return [
                        'id'   => $ws->id,
                        'path' => $ws->path,
                        'sessions' => $wsSessions->map(fn (Session $s) => $this->serializeSession($s, $accounts))->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Sessions whose workspace_id is null but whose JSONL was found. Grouped
     * by their discovered cwd (the original workspace path captured from the
     * JSONL itself, or the encoded directory name if no cwd was recorded).
     * Each group is a candidate "promote to project" target.
     *
     * @return array<int, array{cwd:string, source:string, sessions:array}>
     */
    public function orphanSessions(): array
    {
        $accounts = Account::all()->keyBy('id');
        $rows = Session::with('account')
            ->whereNull('workspace_id')
            ->whereNull('dismissed_at')
            ->orderByDesc('jsonl_mtime')
            ->limit(200)
            ->get();

        $groups = [];
        foreach ($rows as $s) {
            [$key, $source] = $this->orphanGroupKey($s);
            $groups[$key] ??= [
                'cwd'        => $key,
                'source'     => $source,
                'sessions'   => [],
                'latest_at'  => null,
            ];
            $groups[$key]['sessions'][] = $this->serializeSession($s, $accounts);
            $mtime = optional($s->jsonl_mtime)->toIso8601String();
            if ($mtime && (!$groups[$key]['latest_at'] || $mtime > $groups[$key]['latest_at'])) {
                $groups[$key]['latest_at'] = $mtime;
            }
        }

        // Most-recently-active group first.
        usort($groups, function ($a, $b) {
            return strcmp($b['latest_at'] ?? '', $a['latest_at'] ?? '');
        });

        return array_values($groups);
    }

    /**
     * Best available origin label for an orphan: the captured cwd if we have
     * it, else the encoded workspace dir basename (lossy but readable enough
     * to recognize tmo-tools3 in C--wamp-www-tmo-tools3).
     *
     * @return array{0:string,1:string}  [groupKey, source]
     */
    private function orphanGroupKey(Session $s): array
    {
        if (is_string($s->discovered_cwd) && $s->discovered_cwd !== '') {
            return [$s->discovered_cwd, 'cwd'];
        }
        if (is_string($s->jsonl_path) && $s->jsonl_path !== '') {
            $parent = basename(dirname($s->jsonl_path));
            return [$parent !== '' ? $parent : '(unknown)', 'encoded'];
        }
        return ['(unknown)', 'unknown'];
    }

    public function accounts(): array
    {
        return Account::orderBy('is_default', 'desc')
            ->orderBy('label')
            ->get(['id', 'label', 'claude_dir', 'is_default'])
            ->all();
    }

    private function serializeSession(Session $s, Collection $accounts): array
    {
        $account = $accounts->get($s->account_id);
        return [
            'id'             => $s->id,
            'guid'           => $s->guid,
            'guid_short'     => substr($s->guid, 0, 8),
            'account'        => $account ? [
                'id'         => $account->id,
                'label'      => $account->label,
                'is_default' => (bool) $account->is_default,
            ] : null,
            'label'          => $s->label,
            'first_user_prompt' => $s->first_user_prompt,
            'discovered_cwd' => $s->discovered_cwd,
            'status'         => $s->status,
            'jsonl_size_kb'  => $s->jsonl_size_bytes ? (int) round($s->jsonl_size_bytes / 1024) : null,
            'jsonl_mtime'    => optional($s->jsonl_mtime)->toIso8601String(),
            'last_active_at' => optional($s->jsonl_mtime)->toIso8601String(),
            'registered'     => (bool) $s->registered,
        ];
    }
}
