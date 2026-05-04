<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'projects'             => $this->dashboard->tree(),
            'orphans'              => $this->dashboard->orphanSessions(),
            'accounts'             => $this->dashboard->accounts(),
            'register_prompt'      => $this->buildRegisterPrompt(),
            'commandcenter_home'   => env('COMMANDCENTER_HOME') ?: getenv('COMMANDCENTER_HOME') ?: base_path(),
        ]);
    }

    /**
     * The "paste this into any chat" block. Resolves COMMANDCENTER_HOME so
     * the absolute path to artisan is in the command, and passes --workspace
     * explicitly so the registrar still sees the *original* cwd. (cd-then-php
     * would clobber getcwd(), and PowerShell doesn't cd at all -- using
     * --workspace makes both shells behave identically.)
     */
    private function buildRegisterPrompt(): string
    {
        $rawHome = env('COMMANDCENTER_HOME') ?: getenv('COMMANDCENTER_HOME') ?: base_path();
        // Normalize slashes per shell so the rendered prompt looks native:
        // bash uses forward slashes (PHP accepts them on Windows too),
        // PowerShell uses backslashes.
        $bashHome = str_replace('\\', '/', $rawHome);
        $psHome   = str_replace('/', '\\', $rawHome);

        return <<<MARKDOWN
        ## Session Tracking

        When you start working, register this session with Command Center.
        Do NOT investigate the commandcenter project, do NOT cd into it, and do NOT read its files. Just run the command.

        - bash / zsh:
              php "{$bashHome}/artisan" cc:register --workspace "\$PWD" --label "PROJECTNAME | brief task description"

        - PowerShell:
              & php "{$psHome}\\artisan" cc:register --workspace "\$PWD" --label "PROJECTNAME | brief task description"

        Replace PROJECTNAME with this project's name. The description should be 3-5 words summarizing what we are working on.
        GUID and account are auto-detected from the workspace path.
        MARKDOWN;
    }

    public function updateSession(Request $request, string $guid): JsonResponse
    {
        $data = $request->validate([
            'label'     => ['nullable', 'string', 'max:255'],
            'status'    => ['nullable', Rule::in(['active', 'paused', 'done'])],
            'dismissed' => ['nullable', 'boolean'],
        ]);

        $session = Session::where('guid', $guid)->firstOrFail();
        if (array_key_exists('label', $data) && $data['label'] !== null)   $session->label = $data['label'];
        if (array_key_exists('status', $data) && $data['status'] !== null) $session->status = $data['status'];
        if (array_key_exists('dismissed', $data) && $data['dismissed'] !== null) {
            $session->dismissed_at = $data['dismissed'] ? Carbon::now() : null;
        }
        $session->save();

        return response()->json([
            'guid'         => $session->guid,
            'label'        => $session->label,
            'status'       => $session->status,
            'dismissed_at' => optional($session->dismissed_at)->toIso8601String(),
        ]);
    }

    /**
     * Dismiss every orphan session that shares a discovered cwd. Used by the
     * "dismiss all" button on each orphan group. The group key is either the
     * captured cwd (when present) or the encoded jsonl-parent dir basename
     * (the fallback we use when the JSONL had no cwd field).
     */
    public function dismissOrphanGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cwd' => ['required', 'string'],
        ]);
        $cwd = $data['cwd'];
        $now = Carbon::now();

        $candidates = Session::query()
            ->whereNull('workspace_id')
            ->whereNull('dismissed_at')
            ->get();

        $matchIds = $candidates->filter(function ($s) use ($cwd) {
            if ($s->discovered_cwd === $cwd) return true;
            if (!$s->discovered_cwd && is_string($s->jsonl_path) && $s->jsonl_path !== '') {
                return basename(dirname($s->jsonl_path)) === $cwd;
            }
            return false;
        })->pluck('id');

        if ($matchIds->isEmpty()) {
            return response()->json(['dismissed' => 0]);
        }

        Session::whereIn('id', $matchIds)->update(['dismissed_at' => $now]);
        return response()->json(['dismissed' => $matchIds->count()]);
    }
}
