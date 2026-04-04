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

    it('adds exam zoom controls for standalone images and tables', function () {
        var manager = createManager();
        var html = manager.renderExamRichHtml('<p><img src="/diagram.png" alt="Diagram" /></p><table><tbody><tr><td>Isi</td></tr></tbody></table>', {
            context: 'question'
        });

        expect(html).toContain('cbt-rich-zoom-target--image');
        expect(html).toContain('cbt-rich-zoom-target--table');
        expect(html).toContain('data-action="open-rich-zoom"');
        expect(html).toContain('Perbesar');
    });

    it('groups multiple images in one question area into a shared gallery', function () {
        var manager = createManager();
        var html = manager.renderExamRichHtml('<p><img src="/a.png" alt="A" /><img src="/b.png" alt="B" /><img src="/c.png" alt="C" /></p>', {
            context: 'question'
        });

        expect((html.match(/cbt-rich-zoom-target--image/g) || []).length).toBe(3);
        expect((html.match(/data-rich-zoom-gallery-count="3"/g) || []).length).toBe(3);
        expect((html.match(/data-rich-zoom-gallery-id="/g) || []).length).toBe(3);
    });

    it('keeps a single image as a non-gallery zoom target', function () {
        var manager = createManager();
        var html = manager.renderExamRichHtml('<p><img src="/solo.png" alt="Solo" /></p>', {
            context: 'option'
        });

        expect(html).toContain('cbt-rich-zoom-target--image');
        expect(html).not.toContain('data-rich-zoom-gallery-id=');
        expect(html).not.toContain('data-rich-zoom-gallery-count=');
    });
});
