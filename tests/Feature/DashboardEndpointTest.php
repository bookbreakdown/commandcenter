<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Project;
use App\Models\Session;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_full_tree_with_account_labels(): void
    {
        $personal = Account::create(['label' => 'personal', 'claude_dir' => '/tmp/.claude', 'is_default' => true]);
        $savvior  = Account::create(['label' => 'savvior',  'claude_dir' => '/tmp/.claude-savvior', 'is_default' => false]);

        $project = Project::create(['name' => 'TMO']);
        $ws = Workspace::create(['project_id' => $project->id, 'path' => '/foo']);

        Session::create([
            'guid' => '11111111-aaaa-bbbb-cccc-dddddddddddd',
            'account_id' => $savvior->id,
            'workspace_id' => $ws->id,
            'label' => 'TMO | sync rewrite',
            'status' => 'active',
            'registered' => true,
            'jsonl_mtime' => now(),
        ]);

        $response = $this->getJson('/api/dashboard');
        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['projects']);
        $this->assertSame('TMO', $data['projects'][0]['name']);
        $this->assertCount(1, $data['projects'][0]['workspaces']);
        $session = $data['projects'][0]['workspaces'][0]['sessions'][0];
        $this->assertSame('savvior', $session['account']['label']);
        $this->assertSame('TMO | sync rewrite', $session['label']);
        $this->assertSame(2, count($data['accounts']));
    }

    public function test_patch_session_updates_label_and_status(): void
    {
        $account = Account::create(['label' => 'personal', 'claude_dir' => '/tmp/.claude', 'is_default' => true]);
        $project = Project::create(['name' => 'TMO']);
        $ws = Workspace::create(['project_id' => $project->id, 'path' => '/foo']);

        Session::create([
            'guid' => '22222222-aaaa-bbbb-cccc-dddddddddddd',
            'account_id' => $account->id,
            'workspace_id' => $ws->id,
            'jsonl_mtime' => now(),
        ]);

        $this->patchJson('/api/sessions/22222222-aaaa-bbbb-cccc-dddddddddddd', [
            'label' => 'new label',
            'status' => 'paused',
        ])->assertOk();

        $this->assertDatabaseHas('claude_sessions', [
            'guid' => '22222222-aaaa-bbbb-cccc-dddddddddddd',
            'label' => 'new label',
            'status' => 'paused',
        ]);
    }
}
