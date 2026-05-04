import { useCallback, useEffect, useState } from 'react';
import { RefreshCw, Layers } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ProjectCard } from '@/components/ProjectCard';
import { fetchDashboard, type DashboardPayload } from '@/lib/api';

const POLL_MS = 5000;

export function Dashboard() {
    const [data, setData] = useState<DashboardPayload | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

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

    return (
        <div className="mx-auto max-w-6xl px-6 py-6">
            <header className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Layers className="h-5 w-5 text-zinc-700" />
                    <h1 className="text-xl font-semibold tracking-tight text-zinc-900">Command Center</h1>
                </div>
                <div className="flex items-center gap-3">
                    {data?.accounts.map((a) => (
                        <Badge key={a.id} variant={a.label === 'savvior' ? 'savvior' : a.label === 'personal' ? 'personal' : 'default'}>
                            {a.label.toUpperCase()}{a.is_default ? ' • default' : ''}
                        </Badge>
                    ))}
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
                        data.projects.map((p) => <ProjectCard key={p.id} project={p} onChanged={load} />)
                    )}

                    {data.orphans.length > 0 && (
                        <section>
                            <h2 className="mb-2 text-sm font-medium text-zinc-600">Orphan sessions</h2>
                            <div className="rounded-md border border-zinc-200 bg-white">
                                <ul className="divide-y divide-zinc-100">
                                    {data.orphans.map((s) => (
                                        <li key={s.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                                            <Badge variant={s.account?.label === 'savvior' ? 'savvior' : 'personal'}>
                                                {s.account?.label?.toUpperCase() ?? 'UNKNOWN'}
                                            </Badge>
                                            <span className="font-mono text-xs text-zinc-500">{s.guid_short}</span>
                                            <span className="text-zinc-700">{s.label ?? <em className="text-zinc-400">unlabeled</em>}</span>
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
