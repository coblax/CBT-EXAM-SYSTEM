export function escapeHtml(value) {
    var normalized = value === null || value === undefined ? '' : value;
    return String(normalized)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
