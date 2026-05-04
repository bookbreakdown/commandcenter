export function relativeTime(iso: string | null): string {
    if (!iso) return '—';
    const then = new Date(iso).getTime();
    const now = Date.now();
    const diffSec = Math.max(0, Math.floor((now - then) / 1000));
    if (diffSec < 60)            return `${diffSec}s ago`;
    if (diffSec < 60 * 60)       return `${Math.floor(diffSec / 60)}m ago`;
    if (diffSec < 60 * 60 * 24)  return `${Math.floor(diffSec / 3600)}h ago`;
    return `${Math.floor(diffSec / 86400)}d ago`;
}

export function shortGuid(guid: string): string {
    return guid.slice(0, 8);
}
