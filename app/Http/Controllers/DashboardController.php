<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $home = env('COMMANDCENTER_HOME') ?: getenv('COMMANDCENTER_HOME') ?: base_path();
        return <<<MARKDOWN
        ## Session Tracking

        When you start working, register this session with Command Center.
        Do NOT investigate the commandcenter project, do NOT cd into it, and do NOT read its files. Just run the command.

        - bash / zsh:
              php "{$home}/artisan" cc:register --workspace "\$PWD" --label "PROJECTNAME | brief task description"

        - PowerShell:
              & php "{$home}\\artisan" cc:register --workspace "\$PWD" --label "PROJECTNAME | brief task description"

        Replace PROJECTNAME with this project's name. The description should be 3-5 words summarizing what we are working on.
        GUID and account are auto-detected from the workspace path.
        MARKDOWN;
    }

    public function updateSession(Request $request, string $guid): JsonResponse
    {
        $data = $request->validate([
            'label'  => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'done'])],
        ]);

        $session = Session::where('guid', $guid)->firstOrFail();
        $session->fill(array_filter($data, fn ($v) => $v !== null));
        $session->save();

        return response()->json([
            'guid'   => $session->guid,
            'label'  => $session->label,
            'status' => $session->status,
        ]);
    }
}
