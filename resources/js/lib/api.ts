export type Account = {
    id: number;
    label: string;
    is_default: boolean;
    claude_dir?: string;
};

export type SessionRow = {
    id: number;
    guid: string;
    guid_short: string;
    account: { id: number; label: string; is_default: boolean } | null;
    label: string | null;
    status: 'active' | 'paused' | 'done';
    jsonl_size_kb: number | null;
    jsonl_mtime: string | null;
    last_active_at: string | null;
    registered: boolean;
};

export type WorkspaceRow = {
    id: number;
    path: string;
    sessions: SessionRow[];
};

export type ProjectNode = {
    id: number;
    name: string;
    workspaces: WorkspaceRow[];
};

export type DashboardPayload = {
    projects: ProjectNode[];
    orphans: SessionRow[];
    accounts: Account[];
};

export async function fetchDashboard(): Promise<DashboardPayload> {
    const res = await fetch('/api/dashboard', { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`Dashboard fetch failed: ${res.status}`);
    return res.json();
}

export async function patchSession(guid: string, patch: Partial<Pick<SessionRow, 'label' | 'status'>>) {
    const res = await fetch(`/api/sessions/${encodeURIComponent(guid)}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(patch),
    });
    if (!res.ok) throw new Error(`Patch failed: ${res.status}`);
    return res.json();
}
