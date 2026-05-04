/**
 * navigator.clipboard.writeText only works in secure contexts (HTTPS or
 * localhost). On *.test served via plain HTTP it silently rejects, so we
 * fall back to the legacy document.execCommand("copy") via a hidden
 * textarea. Returns true on success.
 */
export async function copyToClipboard(text: string): Promise<boolean> {
    // Try the modern API first when available.
    if (typeof navigator !== 'undefined' && navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Fall through to legacy.
        }
    }

    return legacyCopy(text);
}

function legacyCopy(text: string): boolean {
    if (typeof document === 'undefined') return false;
    const ta = document.createElement('textarea');
    ta.value = text;
    // Position offscreen but keep it focusable; some browsers refuse to copy
    // from elements with display:none or zero width.
    ta.style.position = 'fixed';
    ta.style.top = '0';
    ta.style.left = '0';
    ta.style.width = '1px';
    ta.style.height = '1px';
    ta.style.padding = '0';
    ta.style.border = 'none';
    ta.style.outline = 'none';
    ta.style.boxShadow = 'none';
    ta.style.background = 'transparent';
    ta.setAttribute('readonly', '');
    document.body.appendChild(ta);

    const prevSelection = document.getSelection();
    const prevRange = prevSelection && prevSelection.rangeCount > 0 ? prevSelection.getRangeAt(0) : null;

    ta.select();
    ta.setSelectionRange(0, text.length);

    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }

    document.body.removeChild(ta);

    if (prevRange && prevSelection) {
        prevSelection.removeAllRanges();
        prevSelection.addRange(prevRange);
    }

    return ok;
}
