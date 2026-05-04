import { useCallback, useEffect, useMemo, useState } from 'react';
import { RefreshCw, Layers, ClipboardCopy, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ProjectCard } from '@/components/ProjectCard';
import { fetchDashboard, type DashboardPayload } from '@/lib/api';
import { loadExpanded, saveExpanded } from '@/lib/collapse';
import { copyToClipboard } from '@/lib/clipboard';

const POLL_MS = 5000;

export function Dashboard() {
    const [data, setData] = useState<DashboardPayload | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [expanded, setExpanded] = useState<Set<number>>(() => loadExpanded());
    const [copied, setCopied] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const next = await fetchDashboard();
            setData(next);
            setError(null);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
        const id = window.setInterval(load, POLL_MS);
        return () => window.clearInterval(id);
    }, [load]);

    const toggleWorkspace = useCallback((id: number) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            saveExpanded(next);
            return next;
        });
    }, []);

    const expandAll = useCallback(() => {
        if (!data) return;
        const next = new Set<number>();
        for (const p of data.projects) for (const w of p.workspaces) next.add(w.id);
        saveExpanded(next);
        setExpanded(next);
    }, [data]);

    const collapseAll = useCallback(() => {
        const next = new Set<number>();
        saveExpanded(next);
        setExpanded(next);
    }, []);

    const copyRegisterPrompt = useCallback(async () => {
        if (!data?.register_prompt) return;
        const ok = await copyToClipboard(data.register_prompt);
        if (ok) {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } else {
            setError('Copy failed -- select the prompt manually.');
        }
    }, [data]);

    const totalSessions = useMemo(() => {
        if (!data) return 0;
        let n = 0;
        for (const p of data.projects) for (const w of p.workspaces) n += w.sessions.length;
        return n;
    }, [data]);

    return (
        <div className="mx-auto max-w-6xl px-6 py-6">
            <header className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Layers className="h-5 w-5 text-zinc-700" />
                    <h1 className="text-xl font-semibold tracking-tight text-zinc-900">Command Center</h1>
                    {data && (
                        <span className="text-xs text-zinc-400">
                            {totalSessions} session{totalSessions === 1 ? '' : 's'}
                        </span>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {data?.accounts.map((a) => (
                        <Badge key={a.id} variant={a.label === 'savvior' ? 'savvior' : a.label === 'personal' ? 'personal' : 'default'}>
                            {a.label.toUpperCase()}{a.is_default ? ' • default' : ''}
                        </Badge>
                    ))}
                    <Button size="sm" variant="outline" onClick={copyRegisterPrompt} disabled={!data}>
                        {copied ? <Check className="h-3.5 w-3.5" /> : <ClipboardCopy className="h-3.5 w-3.5" />}
                        {copied ? 'Copied' : 'Copy register prompt'}
                    </Button>
                    <Button size="sm" variant="ghost" onClick={expandAll} disabled={!data}>Expand all</Button>
                    <Button size="sm" variant="ghost" onClick={collapseAll} disabled={!data}>Collapse all</Button>
                    <Button size="sm" variant="outline" onClick={load} disabled={loading}>
                        <RefreshCw className={loading ? 'h-3.5 w-3.5 animate-spin' : 'h-3.5 w-3.5'} />
                        Refresh
                    </Button>
                </div>
            </header>

            {error && (
                <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </div>
            )}

            {!data ? (
                <div className="text-sm text-zinc-500">Loading…</div>
            ) : (
                <div className="space-y-4">
                    {data.projects.length === 0 ? (
                        <div className="rounded-md border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500">
                            No projects yet. Add one with{' '}
                            <code className="rounded bg-zinc-100 px-1.5 py-0.5">php artisan cc:project:add &lt;name&gt;</code>.
                        </div>
                    ) : (
                        data.projects.map((p) => (
                            <ProjectCard
                                key={p.id}
                                project={p}
                                expandedSet={expanded}
                                onToggleWorkspace={toggleWorkspace}
                                onChanged={load}
                            />
                        ))
                    )}

                    {data.orphans.length > 0 && (
                        <section>
                            <h2 className="mb-2 text-sm font-medium text-zinc-600">Orphan sessions</h2>
                            <div className="rounded-md border border-zinc-200 bg-white">
                                <ul className="divide-y divide-zinc-100">
                                    {data.orphans.map((s) => (
                                        <li key={s.id} className="flex flex-col gap-0.5 px-3 py-2 text-sm">
                                            <div className="flex items-center gap-3">
                                                <Badge variant={s.account?.label === 'savvior' ? 'savvior' : 'personal'}>
                                                    {s.account?.label?.toUpperCase() ?? 'UNKNOWN'}
                                                </Badge>
                                                <span className="font-mono text-xs text-zinc-500">{s.guid_short}</span>
                                                <span className="text-zinc-700">{s.label ?? <em className="text-zinc-400">unlabeled</em>}</span>
                                            </div>
                                            {!s.label && s.first_user_prompt && (
                                                <div className="pl-1 text-xs text-zinc-500">{s.first_user_prompt}</div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </section>
                    )}
                </div>
            )}
        </div>
    );
}
