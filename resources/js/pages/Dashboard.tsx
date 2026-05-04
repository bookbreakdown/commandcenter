import { useCallback, useEffect, useMemo, useState } from 'react';
import { RefreshCw, Layers, ClipboardCopy, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ProjectCard } from '@/components/ProjectCard';
import { OrphanGroupCard } from '@/components/OrphanGroupCard';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { fetchDashboard, type DashboardPayload } from '@/lib/api';
import { loadExpanded, saveExpanded } from '@/lib/collapse';
import { copyToClipboard } from '@/lib/clipboard';

const POLL_MS = 5000;

export function Dashboard() {
    const [data, setData] = useState<DashboardPayload | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [expanded, setExpanded] = useState<Set<number>>(() => loadExpanded());
    const [orphanExpanded, setOrphanExpanded] = useState<Set<string>>(new Set());
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

    const toggleOrphan = useCallback((cwd: string) => {
        setOrphanExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(cwd)) next.delete(cwd);
            else next.add(cwd);
            return next;
        });
    }, []);

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
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={copyRegisterPrompt}
                        disabled={!data}
                        className={copied ? 'border-green-300 bg-green-50 text-green-700 hover:bg-green-50' : undefined}
                    >
                        {copied ? <Check className="h-3.5 w-3.5" /> : <ClipboardCopy className="h-3.5 w-3.5" />}
                        {copied ? 'Copied to clipboard' : 'Copy register prompt'}
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
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <span>Orphan sessions</span>
                                    <span className="text-xs font-normal text-zinc-400">
                                        {data.orphans.length} origin{data.orphans.length === 1 ? '' : 's'}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <div>
                                {data.orphans.map((g) => (
                                    <OrphanGroupCard
                                        key={g.cwd}
                                        group={g}
                                        expanded={orphanExpanded.has(g.cwd)}
                                        onToggle={() => toggleOrphan(g.cwd)}
                                        onChanged={load}
                                    />
                                ))}
                            </div>
                        </Card>
                    )}
                </div>
            )}
        </div>
    );
}
