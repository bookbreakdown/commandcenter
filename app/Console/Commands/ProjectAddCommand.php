<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

class ProjectAddCommand extends Command
{
    protected $signature = 'cc:project:add {name : The project label}';
    protected $description = 'Create a project (idempotent).';

    public function handle(): int
    {
        $name = $this->argument('name');
        $project = Project::firstOrCreate(['name' => $name]);
        $this->info("Project [{$project->id}] {$project->name} ".($project->wasRecentlyCreated ? 'created' : 'already exists'));
        return self::SUCCESS;
    }
}
