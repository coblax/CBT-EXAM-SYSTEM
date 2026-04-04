import { describe, expect, it } from 'vitest';
import { createAppMetaManager } from '../../../src/frontend/app/core/app-meta.js';

function createManager() {
    return createAppMetaManager({
        config: {},
        escapeHtml: function (value) {
            return String(value || '');
        },
        state: {},
        windowRef: window
    });
}

describe('createAppMetaManager safeRichHtml', function () {
    it('wraps rich text tables in a horizontal scroll container', function () {
        var manager = createManager();
        var html = manager.safeRichHtml('<p>Opsi</p><table><tbody><tr><th>Field</th><th>Isi</th></tr><tr><td>Key</td><td>C</td></tr></tbody></table>');

        expect(html).toContain('class="cbt-rich-table-wrap"');
        expect(html).toContain('class="cbt-rich-content-table"');
    });

    it('does not double wrap tables that are already inside the rich table wrapper', function () {
        var manager = createManager();
        var html = manager.safeRichHtml('<div class="cbt-rich-table-wrap"><table class="cbt-rich-content-table"><tbody><tr><td>Sudah</td></tr></tbody></table></div>');

        expect((html.match(/cbt-rich-table-wrap/g) || []).length).toBe(1);
        expect((html.match(/cbt-rich-content-table/g) || []).length).toBe(1);
    });
});
