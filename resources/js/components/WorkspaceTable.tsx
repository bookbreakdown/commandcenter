import { Folder } from 'lucide-react';
import { SessionRow } from '@/components/SessionRow';
import type { WorkspaceRow as WorkspaceT } from '@/lib/api';

type Props = {
    workspace: WorkspaceT;
    onChanged: () => void;
};

export function WorkspaceTable({ workspace, onChanged }: Props) {
    return (
        <div className="border-t border-zinc-100">
            <div className="flex items-center gap-2 bg-zinc-50 px-3 py-2 text-xs text-zinc-600">
                <Folder className="h-3.5 w-3.5 text-zinc-400" />
                <span className="font-mono">{workspace.path}</span>
                <span className="ml-auto text-zinc-400">{workspace.sessions.length} session{workspace.sessions.length === 1 ? '' : 's'}</span>
            </div>
            {workspace.sessions.length === 0 ? (
                <div className="px-3 py-4 text-sm italic text-zinc-400">no sessions discovered</div>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase text-zinc-400">
                            <th className="px-3 py-1.5 font-medium">Account</th>
                            <th className="px-3 py-1.5 font-medium">GUID</th>
                            <th className="px-3 py-1.5 font-medium">Label</th>
                            <th className="px-3 py-1.5 font-medium">Status</th>
                            <th className="px-3 py-1.5 font-medium">Last Active</th>
                            <th className="px-3 py-1.5 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {workspace.sessions.map((s) => (
                            <SessionRow key={s.id} session={s} onChanged={onChanged} />
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
