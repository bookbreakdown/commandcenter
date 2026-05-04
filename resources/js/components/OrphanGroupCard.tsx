import { ChevronDown, ChevronRight, Folder, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { SessionRow, SessionPeek } from '@/components/SessionRow';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dismissOrphanGroup, type OrphanGroup } from '@/lib/api';

type Props = {
    group: OrphanGroup;
    expanded: boolean;
    onToggle: () => void;
    onChanged: () => void;
};

export function OrphanGroupCard({ group, expanded, onToggle, onChanged }: Props) {
    const Chevron = expanded ? ChevronDown : ChevronRight;
    const mostRecent = group.sessions[0];
    const [busy, setBusy] = useState(false);

    const dismissAll: React.MouseEventHandler<HTMLButtonElement> = async (e) => {
        e.stopPropagation();
        if (!confirm(`Dismiss all ${group.sessions.length} session${group.sessions.length === 1 ? '' : 's'} from ${group.cwd}?`)) {
            return;
        }
        setBusy(true);
        try {
            await dismissOrphanGroup(group.cwd);
            onChanged();
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="border-t border-zinc-100">
            <div className="flex items-start gap-2 bg-zinc-50 px-3 py-2 text-xs text-zinc-600">
                <button
                    type="button"
                    onClick={onToggle}
                    className="flex min-w-0 flex-1 items-start gap-2 text-left hover:text-zinc-900"
                >
                    <Chevron className="mt-0.5 h-3.5 w-3.5 shrink-0 text-zinc-400" />
                    <Folder className="mt-0.5 h-3.5 w-3.5 shrink-0 text-zinc-400" />
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <span className="font-mono text-zinc-700">{group.cwd}</span>
                            {group.source === 'encoded' && (
                                <Badge variant="muted" className="ml-1">encoded</Badge>
                            )}
                            <span className="ml-auto whitespace-nowrap text-zinc-400">
                                {group.sessions.length} session{group.sessions.length === 1 ? '' : 's'}
                            </span>
                        </div>
                        {!expanded && mostRecent && (
                            <div className="mt-1 truncate">
                                <SessionPeek session={mostRecent} />
                            </div>
                        )}
                    </div>
                </button>
                <Button size="xs" variant="ghost" disabled={busy} onClick={dismissAll} title="Dismiss all sessions in this group">
                    <EyeOff className="h-3.5 w-3.5" />
                    Dismiss all
                </Button>
            </div>

            {expanded && (
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
                        {group.sessions.map((s) => (
                            <SessionRow key={s.id} session={s} onChanged={onChanged} dismissable />
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
