import { Copy, Pause, CheckCircle2, Play, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { patchSession, type SessionRow as SessionT } from '@/lib/api';
import { relativeTime } from '@/lib/format';
import { copyToClipboard } from '@/lib/clipboard';
import { useState } from 'react';

type Props = {
    session: SessionT;
    onChanged: () => void;
    dismissable?: boolean;
};

const accountVariant = (label: string | undefined): 'personal' | 'savvior' | 'muted' => {
    if (label === 'personal') return 'personal';
    if (label === 'savvior')  return 'savvior';
    return 'muted';
};

const statusVariant = (s: SessionT['status']): 'active' | 'paused' | 'done' => s;

export function SessionRow({ session, onChanged, dismissable = false }: Props) {
    const [busy, setBusy] = useState(false);

    const copyResume = async () => {
        // Include --dangerously-skip-permissions so the resumed session lands
        // in the same permission mode it ran in originally (this user's
        // workflow always runs Claude that way).
        await copyToClipboard(`claude --dangerously-skip-permissions --resume ${session.guid}`);
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

    const dismiss = async () => {
        setBusy(true);
        try {
            await patchSession(session.guid, { dismissed: true });
            onChanged();
        } finally {
            setBusy(false);
        }
    };

    const showPreview = !session.label && session.first_user_prompt;

    return (
        <>
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
                        {dismissable && (
                            <Button size="xs" variant="ghost" disabled={busy} onClick={dismiss} title="Dismiss this session">
                                <X className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                </td>
            </tr>
            {showPreview && (
                <tr className="border-t border-zinc-50 bg-zinc-50/50">
                    <td colSpan={6} className="px-3 py-1.5">
                        <span className="text-[11px] uppercase tracking-wide text-zinc-400">first prompt:</span>{' '}
                        <span className="text-xs text-zinc-600">{session.first_user_prompt}</span>
                    </td>
                </tr>
            )}
        </>
    );
}

/**
 * Compact one-line peek used inside a collapsed workspace header so the most
 * recent session is visible without expanding. No actions, no preview row.
 */
export function SessionPeek({ session }: { session: SessionT }) {
    return (
        <div className="flex items-center gap-2 truncate text-xs">
            <Badge variant={accountVariant(session.account?.label)}>
                {session.account?.label?.toUpperCase() ?? 'UNKNOWN'}
            </Badge>
            <Badge variant={statusVariant(session.status)}>{session.status}</Badge>
            <span className="font-mono text-zinc-500">{session.guid_short}</span>
            <span className="truncate text-zinc-700">
                {session.label ?? <em className="text-zinc-400">{session.first_user_prompt ?? 'unlabeled'}</em>}
            </span>
            <span className="ml-auto whitespace-nowrap text-zinc-400">{relativeTime(session.last_active_at)}</span>
        </div>
    );
}
