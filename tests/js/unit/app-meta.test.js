import { describe, expect, it } from 'vitest';
import { createAppMetaManager } from '../../../src/frontend/app/core/app-meta.js';

function createManager(overrides = {}) {
    return createAppMetaManager({
        config: overrides.config || {},
        escapeHtml: function (value) {
            return String(value || '');
        },
        state: overrides.state || {},
        windowRef: overrides.windowRef || window
    });
}

function createManagerWithWindow(windowRef) {
    return createAppMetaManager({
        config: {},
        escapeHtml: function (value) {
            return String(value || '');
        },
        state: {},
        windowRef: windowRef
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

describe('createAppMetaManager normalizePhotoUrl', function () {
    it('rewrites stale private-network WordPress asset urls to the current host path', function () {
        var manager = createManagerWithWindow({
            location: {
                origin: 'https://exam.example.test'
            }
        });

        expect(manager.normalizePhotoUrl('http://192.168.1.9/wp-content/plugins/cbt-exam-system/public/Default%20Pria.png'))
            .toBe('/wp-content/plugins/cbt-exam-system/public/Default%20Pria.png');
    });

    it('rewrites stale public-domain WordPress asset urls to the current host', function () {
        var manager = createManagerWithWindow({
            location: {
                origin: 'https://exam.example.test'
            }
        });

        expect(manager.normalizePhotoUrl('https://old-domain.test/wp-content/uploads/cbt-user-import-photos/siswa-a.jpg'))
            .toBe('https://exam.example.test/wp-content/uploads/cbt-user-import-photos/siswa-a.jpg');
    });

    it('keeps external cdn photo urls untouched', function () {
        var manager = createManagerWithWindow({
            location: {
                origin: 'https://exam.example.test'
            }
        });

        expect(manager.normalizePhotoUrl('https://cdn.example.com/avatar.jpg'))
            .toBe('https://cdn.example.com/avatar.jpg');
    });
});

describe('createAppMetaManager renderAlert', function () {
    it('renders a dismiss button for explicit error messages', function () {
        var manager = createManager({
            state: {
                error: 'Mode fullscreen wajib aktif untuk ujian ini.'
            }
        });

        var html = manager.renderAlert();

        expect(html).toContain('cbt-alert-error');
        expect(html).toContain('data-action="dismiss-alert"');
        expect(html).toContain('Tutup informasi');
    });

    it('keeps sync status alerts non-dismissible because they reflect live runtime state', function () {
        var manager = createManager({
            state: {
                attemptId: 77,
                connectionStatus: 'online',
                pendingSyncCount: 2,
                lastSyncError: '',
                stage: 'exam'
            }
        });

        var html = manager.renderAlert();

        expect(html).toContain('cbt-alert-warning');
        expect(html).not.toContain('data-action="dismiss-alert"');
    });
});
