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
     * The "paste this into any chat" block. Resolves COMMANDCENTER_HOME so the
     * absolute path to artisan is in the command -- the agent can run it
     * without grepping the project for paths first.
     */
    private function buildRegisterPrompt(): string
    {
        $home = env('COMMANDCENTER_HOME') ?: getenv('COMMANDCENTER_HOME') ?: base_path();
        // Normalize backslashes for both shells in the rendered block; the bash
        // form quotes the path so backslashes are literal regardless.
        return <<<MARKDOWN
        ## Session Tracking

        When you start working, register this session with Command Center.
        Do NOT investigate the commandcenter project, do NOT cd into it, and do NOT read its files. Just run the command.

        - bash / zsh:
              ( cd "{$home}" && php artisan cc:register --label "PROJECTNAME | brief task description" )

        - PowerShell:
              & php "{$home}\\artisan" cc:register --label "PROJECTNAME | brief task description"

        Replace PROJECTNAME with this project's name. The description should be 3-5 words summarizing what we are working on.
        GUID, account, and workspace are auto-detected from your current working directory.
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
