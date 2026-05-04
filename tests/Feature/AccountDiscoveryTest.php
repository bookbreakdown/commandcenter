<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Services\ClaudeAccountDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $fakeHome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cc-test-' . uniqid('home_');
        mkdir($this->fakeHome, 0777, true);
        config(['app.cc_test_home_root' => $this->fakeHome]);
        // We use env() in the service, so override at runtime via $_ENV.
        $_ENV['COMMANDCENTER_HOME_ROOT'] = $this->fakeHome;
        putenv('COMMANDCENTER_HOME_ROOT=' . $this->fakeHome);
    }

    protected function tearDown(): void
    {
        unset($_ENV['COMMANDCENTER_HOME_ROOT']);
        putenv('COMMANDCENTER_HOME_ROOT');
        $this->rrmdir($this->fakeHome);
        parent::tearDown();
    }

    public function test_finds_default_claude_dir_with_projects(): void
    {
        mkdir($this->fakeHome . '/.claude/projects', 0777, true);

        $accounts = (new ClaudeAccountDiscovery)->scan();

        $this->assertCount(1, $accounts);
        $this->assertSame('personal', $accounts[0]['label']);
        $this->assertTrue($accounts[0]['is_default']);
    }

    public function test_finds_named_claude_dir(): void
    {
        mkdir($this->fakeHome . '/.claude-savvior/projects', 0777, true);

        $accounts = (new ClaudeAccountDiscovery)->scan();

        $this->assertCount(1, $accounts);
        $this->assertSame('savvior', $accounts[0]['label']);
        $this->assertFalse($accounts[0]['is_default']);
    }

    public function test_finds_multiple_accounts_side_by_side(): void
    {
        mkdir($this->fakeHome . '/.claude/projects', 0777, true);
        mkdir($this->fakeHome . '/.claude-savvior/projects', 0777, true);
        mkdir($this->fakeHome . '/.claude-personal/projects', 0777, true);

        $accounts = (new ClaudeAccountDiscovery)->scan();

        $labels = array_column($accounts, 'label');
        sort($labels);
        $this->assertSame(['personal', 'personal', 'savvior'], $labels); // .claude → "personal", .claude-personal → "personal"
        // Note: collision is intentional test surface; user responsibility to rename.
    }

    public function test_skips_dirs_without_projects_subdir(): void
    {
        mkdir($this->fakeHome . '/.claude', 0777, true); // no projects/
        mkdir($this->fakeHome . '/.claude-savvior/projects', 0777, true);

        $accounts = (new ClaudeAccountDiscovery)->scan();

        $this->assertCount(1, $accounts);
        $this->assertSame('savvior', $accounts[0]['label']);
    }

    public function test_sync_to_database_is_idempotent(): void
    {
        mkdir($this->fakeHome . '/.claude/projects', 0777, true);
        mkdir($this->fakeHome . '/.claude-savvior/projects', 0777, true);

        (new ClaudeAccountDiscovery)->syncToDatabase();
        $first = Account::count();

        (new ClaudeAccountDiscovery)->syncToDatabase();
        $second = Account::count();

        $this->assertSame(2, $first);
        $this->assertSame($first, $second);
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
