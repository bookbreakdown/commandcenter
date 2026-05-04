import { Copy, Pause, CheckCircle2, Play } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { patchSession, type SessionRow as SessionT } from '@/lib/api';
import { relativeTime } from '@/lib/format';
import { useState } from 'react';

type Props = {
    session: SessionT;
    onChanged: () => void;
};

const accountVariant = (label: string | undefined): 'personal' | 'savvior' | 'muted' => {
    if (label === 'personal') return 'personal';
    if (label === 'savvior')  return 'savvior';
    return 'muted';
};

const statusVariant = (s: SessionT['status']): 'active' | 'paused' | 'done' => s;

export function SessionRow({ session, onChanged }: Props) {
    const [busy, setBusy] = useState(false);

    const copyResume = async () => {
        await navigator.clipboard.writeText(`claude --resume ${session.guid}`);
    };

    const setStatus = async (status: SessionT['status']) => {
        setBusy(true);
        try {
            await patchSession(session.guid, { status });
            onChanged();
        } finally {
            setBusy(false);
        }
    };

    return (
        <tr className="border-t border-zinc-100">
            <td className="px-3 py-2 align-middle">
                <Badge variant={accountVariant(session.account?.label)}>
                    {session.account?.label?.toUpperCase() ?? 'UNKNOWN'}
                </Badge>
            </td>
            <td className="px-3 py-2 font-mono text-xs text-zinc-500">{session.guid_short}</td>
            <td className="px-3 py-2">
                {session.label ? (
                    <span className="text-sm text-zinc-900">{session.label}</span>
                ) : (
                    <span className="text-sm italic text-zinc-400">unlabeled</span>
                )}
            </td>
            <td className="px-3 py-2">
                <Badge variant={statusVariant(session.status)}>{session.status}</Badge>
            </td>
            <td className="px-3 py-2 text-xs text-zinc-500">{relativeTime(session.last_active_at)}</td>
            <td className="px-3 py-2 text-right">
                <div className="flex items-center justify-end gap-1">
                    <Button size="xs" variant="ghost" onClick={copyResume} title="Copy resume command">
                        <Copy className="h-3.5 w-3.5" />
                    </Button>
                    {session.status !== 'paused' && (
                        <Button size="xs" variant="ghost" disabled={busy} onClick={() => setStatus('paused')} title="Mark paused">
                            <Pause className="h-3.5 w-3.5" />
                        </Button>
                    )}
                    {session.status !== 'done' && (
                        <Button size="xs" variant="ghost" disabled={busy} onClick={() => setStatus('done')} title="Mark done">
                            <CheckCircle2 className="h-3.5 w-3.5" />
                        </Button>
                    )}
                    {session.status !== 'active' && (
                        <Button size="xs" variant="ghost" disabled={busy} onClick={() => setStatus('active')} title="Mark active">
                            <Play className="h-3.5 w-3.5" />
                        </Button>
                    )}
                </div>
            </td>
        </tr>
    );
}
