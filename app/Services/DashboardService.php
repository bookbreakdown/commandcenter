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
     * Sessions whose workspace_id is null but whose JSONL was found. These are
     * Claude sessions in workspaces the user hasn't registered yet -- showing
     * them in a separate bucket lets the user adopt them with one click later.
     */
    public function orphanSessions(): array
    {
        $accounts = Account::all()->keyBy('id');
        return Session::with('account')
            ->whereNull('workspace_id')
            ->orderByDesc('jsonl_mtime')
            ->limit(50)
            ->get()
            ->map(fn (Session $s) => $this->serializeSession($s, $accounts))
            ->all();
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
            'status'         => $s->status,
            'jsonl_size_kb'  => $s->jsonl_size_bytes ? (int) round($s->jsonl_size_bytes / 1024) : null,
            'jsonl_mtime'    => optional($s->jsonl_mtime)->toIso8601String(),
            'last_active_at' => optional($s->jsonl_mtime)->toIso8601String(),
            'registered'     => (bool) $s->registered,
        ];
    }
}
