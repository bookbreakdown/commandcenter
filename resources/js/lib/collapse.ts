/**
 * localStorage-backed Set of expanded workspace ids. Workspaces collapse by
 * default; opening one persists across reloads. State is per-browser (no
 * users), so localStorage is sufficient -- no DB column needed.
 */
const STORAGE_KEY = 'cc.expandedWorkspaces.v1';

export function loadExpanded(): Set<number> {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return new Set();
        const arr = JSON.parse(raw);
        if (!Array.isArray(arr)) return new Set();
        return new Set(arr.filter((x) => typeof x === 'number'));
    } catch {
        return new Set();
    }
}

export function saveExpanded(ids: Set<number>): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(ids)));
    } catch {
        // Ignore -- private mode or storage full. Behavior degrades to
        // in-memory only.
    }
}
