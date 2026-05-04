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
            'projects' => $this->dashboard->tree(),
            'orphans'  => $this->dashboard->orphanSessions(),
            'accounts' => $this->dashboard->accounts(),
        ]);
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
