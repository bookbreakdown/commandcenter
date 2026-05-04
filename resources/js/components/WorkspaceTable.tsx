import { Folder, ChevronDown, ChevronRight } from 'lucide-react';
import { SessionRow } from '@/components/SessionRow';
import type { WorkspaceRow as WorkspaceT } from '@/lib/api';

type Props = {
    workspace: WorkspaceT;
    expanded: boolean;
    onToggle: () => void;
    onChanged: () => void;
};

export function WorkspaceTable({ workspace, expanded, onToggle, onChanged }: Props) {
    const Chevron = expanded ? ChevronDown : ChevronRight;
    return (
        <div className="border-t border-zinc-100">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center gap-2 bg-zinc-50 px-3 py-2 text-left text-xs text-zinc-600 hover:bg-zinc-100"
            >
                <Chevron className="h-3.5 w-3.5 text-zinc-400" />
                <Folder className="h-3.5 w-3.5 text-zinc-400" />
                <span className="font-mono">{workspace.path}</span>
                <span className="ml-auto text-zinc-400">
                    {workspace.sessions.length} session{workspace.sessions.length === 1 ? '' : 's'}
                </span>
            </button>

            {expanded && (
                workspace.sessions.length === 0 ? (
                    <div className="px-3 py-4 text-sm italic text-zinc-400">no sessions discovered</div>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase text-zinc-400">
                                <th className="px-3 py-1.5 font-medium">Account</th>
                                <th className="px-3 py-1.5 font-medium">GUID</th>
                                <th className="px-3 py-1.5 font-medium">Label / Preview</th>
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
                )
            )}
        </div>
    );
}
