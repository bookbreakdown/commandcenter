<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Console\Command;

class WorkspaceAddCommand extends Command
{
    protected $signature = 'cc:workspace:add
                            {project : Project name (must exist)}
                            {path : Absolute path to the workspace on this PC}';
    protected $description = 'Attach a workspace to a project (idempotent).';

    public function handle(): int
    {
        $projectName = $this->argument('project');
        $path = $this->argument('path');

        $project = Project::where('name', $projectName)->first();
        if (!$project) {
            $this->error("Project not found: {$projectName}. Run cc:project:add first.");
            return self::FAILURE;
        }

        $workspace = Workspace::firstOrCreate(
            ['path' => $path],
            ['project_id' => $project->id]
        );

        if ($workspace->project_id !== $project->id) {
            $this->warn("Workspace exists under a different project (id={$workspace->project_id}); leaving as-is.");
        } else {
            $this->info("Workspace [{$workspace->id}] {$workspace->path} ".($workspace->wasRecentlyCreated ? 'created' : 'already exists'));
        }
        return self::SUCCESS;
    }
}
