import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { WorkspaceTable } from '@/components/WorkspaceTable';
import type { ProjectNode } from '@/lib/api';

type Props = {
    project: ProjectNode;
    expandedSet: Set<number>;
    onToggleWorkspace: (id: number) => void;
    onChanged: () => void;
};

export function ProjectCard({ project, expandedSet, onToggleWorkspace, onChanged }: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between">
                    <span>{project.name}</span>
                    <span className="text-xs font-normal text-zinc-400">
                        {project.workspaces.length} workspace{project.workspaces.length === 1 ? '' : 's'}
                    </span>
                </CardTitle>
            </CardHeader>
            <div>
                {project.workspaces.length === 0 ? (
                    <div className="px-4 py-3 text-sm italic text-zinc-400">No workspaces.</div>
                ) : (
                    project.workspaces.map((ws) => (
                        <WorkspaceTable
                            key={ws.id}
                            workspace={ws}
                            expanded={expandedSet.has(ws.id)}
                            onToggle={() => onToggleWorkspace(ws.id)}
                            onChanged={onChanged}
                        />
                    ))
                )}
            </div>
        </Card>
    );
}
