<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Project;
use App\Models\Session;
use App\Models\Workspace;
use App\Services\ClaudeSessionDiscovery;
use App\Services\WorkspacePathEncoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $fakeHome;
    private Account $personal;
    private Account $savvior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cc-sess-' . uniqid();
        mkdir($this->fakeHome . '/.claude/projects', 0777, true);
        mkdir($this->fakeHome . '/.claude-savvior/projects', 0777, true);

        $this->personal = Account::create([
            'label' => 'personal',
            'claude_dir' => $this->fakeHome . DIRECTORY_SEPARATOR . '.claude',
            'is_default' => true,
        ]);
        $this->savvior = Account::create([
            'label' => 'savvior',
            'claude_dir' => $this->fakeHome . DIRECTORY_SEPARATOR . '.claude-savvior',
            'is_default' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->fakeHome);
        parent::tearDown();
    }

    public function test_discovers_session_jsonl_and_links_to_workspace(): void
    {
        $project = Project::create(['name' => 'TMO']);
        $ws = Workspace::create([
            'project_id' => $project->id,
            'path' => 'C:\\wamp\\www\\tmo-tools3',
        ]);

        $encoded = (new WorkspacePathEncoder)->encode($ws->path);
        $dir = $this->savvior->claude_dir . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $encoded;
        mkdir($dir, 0777, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'aaaaaaaa-1111-2222-3333-444444444444.jsonl', "{}\n");

        $touched = app(ClaudeSessionDiscovery::class)->syncToDatabase();

        $this->assertSame(1, $touched);
        $session = Session::first();
        $this->assertSame('aaaaaaaa-1111-2222-3333-444444444444', $session->guid);
        $this->assertSame($this->savvior->id, $session->account_id);
        $this->assertSame($ws->id, $session->workspace_id);
        $this->assertFalse($session->registered);
        $this->assertNotNull($session->jsonl_mtime);
    }

    public function test_same_workspace_under_two_accounts_yields_two_sessions(): void
    {
        $project = Project::create(['name' => 'TMO']);
        $ws = Workspace::create([
            'project_id' => $project->id,
            'path' => '/var/www/myproject',
        ]);

        $encoded = (new WorkspacePathEncoder)->encode($ws->path);
        foreach ([$this->personal, $this->savvior] as $i => $account) {
            $dir = $account->claude_dir . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $encoded;
            mkdir($dir, 0777, true);
            file_put_contents($dir . "/sess-{$i}-aaaa-bbbb-cccc-dddddddddddd.jsonl", "{}\n");
        }

        app(ClaudeSessionDiscovery::class)->syncToDatabase();

        $sessions = Session::orderBy('account_id')->get();
        $this->assertCount(2, $sessions);
        $this->assertSame($this->personal->id, $sessions[0]->account_id);
        $this->assertSame($this->savvior->id, $sessions[1]->account_id);
    }

    public function test_discovery_is_idempotent_and_does_not_overwrite_human_label(): void
    {
        $project = Project::create(['name' => 'TMO']);
        $ws = Workspace::create([
            'project_id' => $project->id,
            'path' => '/foo',
        ]);
        $encoded = (new WorkspacePathEncoder)->encode($ws->path);
        $dir = $this->savvior->claude_dir . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $encoded;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/aaaa1111-2222-3333-4444-555555555555.jsonl', "{}\n");

        app(ClaudeSessionDiscovery::class)->syncToDatabase();
        $session = Session::first();
        $session->update(['label' => 'TMO | sync rewrite', 'registered' => true, 'status' => 'paused']);

        app(ClaudeSessionDiscovery::class)->syncToDatabase();

        $session->refresh();
        $this->assertSame('TMO | sync rewrite', $session->label);
        $this->assertTrue($session->registered);
        $this->assertSame('paused', $session->status);
    }

    public function test_orphan_session_recorded_when_workspace_unknown(): void
    {
        // No Workspace row at all.
        $encoded = (new WorkspacePathEncoder)->encode('/some/random/path');
        $dir = $this->savvior->claude_dir . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $encoded;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/orph0000-1111-2222-3333-444444444444.jsonl', "{}\n");

        app(ClaudeSessionDiscovery::class)->syncToDatabase();

        $session = Session::first();
        $this->assertNull($session->workspace_id);
        $this->assertSame($this->savvior->id, $session->account_id);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
