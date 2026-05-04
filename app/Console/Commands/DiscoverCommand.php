<?php

namespace App\Console\Commands;

use App\Services\ClaudeAccountDiscovery;
use App\Services\ClaudeSessionDiscovery;
use Illuminate\Console\Command;

class DiscoverCommand extends Command
{
    protected $signature = 'cc:discover';
    protected $description = 'Scan for Claude Code config dirs and refresh accounts + sessions in the DB.';

    public function handle(
        ClaudeAccountDiscovery $accountDiscovery,
        ClaudeSessionDiscovery $sessionDiscovery,
    ): int {
        $home = $accountDiscovery->homeRoot();
        if (!$home) {
            $this->error('Could not resolve a HOME directory. Set COMMANDCENTER_HOME_ROOT or HOME in your .env.');
            return self::FAILURE;
        }
        $this->info("Scanning for .claude* dirs under {$home}");

        $accounts = $accountDiscovery->syncToDatabase();
        $this->line('Accounts:');
        foreach ($accounts as $a) {
            $this->line(sprintf('  [%d] %-15s %s%s', $a->id, $a->label, $a->claude_dir, $a->is_default ? '  (default)' : ''));
        }
        if ($accounts->isEmpty()) {
            $this->warn('No .claude* directories with a projects/ subdir were found.');
            return self::SUCCESS;
        }

        $touched = $sessionDiscovery->syncToDatabase();
        $this->info("Sessions touched: {$touched}");

        return self::SUCCESS;
    }
}
