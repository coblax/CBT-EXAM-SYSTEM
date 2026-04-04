import { describe, expect, it, vi } from 'vitest';
import { createAppEventManager } from '../../../src/frontend/app/core/app-events.js';

function createFixture(overrides = {}) {
    var root = document.createElement('div');
    document.body.appendChild(root);
    var render = overrides.render || vi.fn();
    var state = Object.assign({
        stage: 'exam',
        examPickerMobileOpen: false,
        userPhotoModalOpen: false,
        richZoomModalOpen: false,
        richZoomModalType: '',
        richZoomModalTitle: '',
        richZoomModalMarkup: '',
        richZoomModalGalleryId: '',
        richZoomModalGalleryIndex: 0,
        richZoomModalGalleryItems: [],
        richZoomModalGalleryCount: 0,
        richZoomModalScaleMode: 'fit',
        richZoomModalScalePercent: 100,
        finishConfirmOpen: false,
        isFinishing: false,
        calculatorVisible: false,
        isOpeningAttempt: false
    }, overrides.state || {});

    var manager = createAppEventManager({
        clearMessages: function () {},
        closeFinishConfirmModal: function () {},
        debugManager: null,
        documentRef: document,
        flushAttemptUiStateSilently: function () {},
        flushPendingAnswerBatchSilently: function () {},
        fontScaleDefault: 100,
        fontScaleStep: 10,
        fullLogout: function () {},
        getCurrentUserPhoto: function () {
            return '';
        },
        recordActionTrail: function () {},
        handleAnswerChangeTarget: function () {
            return false;
        },
        handleAnswerInputTarget: function () {
            return false;
        },
        handleArrowNavigationKey: function () {
            return false;
        },
        handleBlockedBrowserInspectionShortcutAction: function () {
            return false;
        },
        handleBlockedClipboardAction: function () {
            return false;
        },
        handleBlockedPrintAction: function () {
            return false;
        },
        handleFinish: function () {},
        handleLogin: function () {},
        handleNavigationAction: function () {
            return false;
        },
        handleStartExam: function () {},
        handleViewResult: function () {},
        isCompactNavViewport: function () {
            return false;
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        isExamClipboardBlockingActive: function () {
            return false;
        },
        isExamFullscreenBlockingActive: function () {
            return false;
        },
        isQuestionRevisionRefreshActive: function () {
            return false;
        },
        loadExams: function () {},
        noteQuestionPrefetchActivity: function () {},
        render: render,
        requestExamFullscreen: function () {
            return Promise.resolve(false);
        },
        resetExamSession: function () {},
        root: root,
        stageRuntimeManager: {
            handleCalculatorAction: function () {},
            handleCalculatorEnterKey: function () {
                return true;
            },
            handleCalculatorInput: function () {},
            toggleCalculator: function () {}
        },
        state: state,
        toggleTheme: function () {},
        updateFontScale: function () {
            return false;
        },
        updateNavPanelPosition: function () {
            return false;
        },
        updateSelectedExam: function () {}
    });

    return {
        manager: manager,
        render: render,
        root: root,
        state: state
    };
}

describe('createAppEventManager rich zoom modal', function () {
    it('opens rich zoom from an option label button without delegating the click elsewhere', function () {
        var fixture = createFixture();
        fixture.root.innerHTML = [
            '<label class="cbt-option">',
            '<input type="radio" name="q1" />',
            '<div class="cbt-option-label">',
            '<div class="cbt-rich-zoom-target cbt-rich-zoom-target--table" data-rich-zoom-type="table" data-rich-zoom-title="Tabel Opsi">',
            '<div class="cbt-rich-zoom-toolbar">',
            '<button class="cbt-rich-zoom-button" data-action="open-rich-zoom" type="button">Perbesar</button>',
            '</div>',
            '<div class="cbt-rich-zoom-source"><div class="cbt-rich-table-wrap"><table class="cbt-rich-content-table"><tbody><tr><td>Isi</td></tr></tbody></table></div></div>',
            '</div>',
            '</div>',
            '</label>'
        ].join('');

        var button = fixture.root.querySelector('[data-action="open-rich-zoom"]');
        var event = {
            preventDefault: vi.fn(),
            stopPropagation: vi.fn(),
            target: button
        };

        var handled = fixture.manager.handleRootClick(event);

        expect(handled).toBe(true);
        expect(event.preventDefault).toHaveBeenCalledTimes(1);
        expect(event.stopPropagation).toHaveBeenCalledTimes(1);
        expect(fixture.state.richZoomModalOpen).toBe(true);
        expect(fixture.state.richZoomModalType).toBe('table');
        expect(fixture.state.richZoomModalTitle).toBe('Tabel Opsi');
        expect(fixture.state.richZoomModalMarkup).toContain('cbt-rich-content-table');
        expect(fixture.state.richZoomModalGalleryCount).toBe(0);
        expect(fixture.state.richZoomModalScaleMode).toBe('fit');
        expect(fixture.state.richZoomModalScalePercent).toBe(100);
        expect(fixture.render).toHaveBeenCalledWith('open-rich-zoom', expect.objectContaining({
            action: 'open-rich-zoom',
            zoomType: 'table'
        }));
    });

    it('opens an image gallery at the clicked index and supports next/prev navigation', function () {
        var fixture = createFixture();
        fixture.root.innerHTML = [
            '<div class="cbt-question-stem">',
            '<div class="cbt-rich-zoom-target cbt-rich-zoom-target--image" data-rich-zoom-type="image" data-rich-zoom-title="Gambar Soal" data-rich-zoom-gallery-id="gallery-77" data-rich-zoom-gallery-index="0" data-rich-zoom-gallery-count="3">',
            '<div class="cbt-rich-zoom-toolbar"><button class="cbt-rich-zoom-button" data-action="open-rich-zoom" type="button">Zoom</button></div>',
            '<div class="cbt-rich-zoom-source"><img src="/a.png" alt="A" /></div>',
            '</div>',
            '<div class="cbt-rich-zoom-target cbt-rich-zoom-target--image" data-rich-zoom-type="image" data-rich-zoom-title="Gambar Soal" data-rich-zoom-gallery-id="gallery-77" data-rich-zoom-gallery-index="1" data-rich-zoom-gallery-count="3">',
            '<div class="cbt-rich-zoom-toolbar"><button class="cbt-rich-zoom-button" data-action="open-rich-zoom" type="button">Zoom</button></div>',
            '<div class="cbt-rich-zoom-source"><img src="/b.png" alt="B" /></div>',
            '</div>',
            '<div class="cbt-rich-zoom-target cbt-rich-zoom-target--image" data-rich-zoom-type="image" data-rich-zoom-title="Gambar Soal" data-rich-zoom-gallery-id="gallery-77" data-rich-zoom-gallery-index="2" data-rich-zoom-gallery-count="3">',
            '<div class="cbt-rich-zoom-toolbar"><button class="cbt-rich-zoom-button" data-action="open-rich-zoom" type="button">Zoom</button></div>',
            '<div class="cbt-rich-zoom-source"><img src="/c.png" alt="C" /></div>',
            '</div>',
            '</div>'
        ].join('');

        var buttons = fixture.root.querySelectorAll('[data-action="open-rich-zoom"]');
        var openHandled = fixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            stopPropagation: vi.fn(),
            target: buttons[1]
        });

        expect(openHandled).toBe(true);
        expect(fixture.state.richZoomModalOpen).toBe(true);
        expect(fixture.state.richZoomModalGalleryId).toBe('gallery-77');
        expect(fixture.state.richZoomModalGalleryIndex).toBe(1);
        expect(fixture.state.richZoomModalGalleryCount).toBe(3);
        expect(fixture.state.richZoomModalMarkup).toContain('/b.png');
        expect(fixture.state.richZoomModalScaleMode).toBe('fit');
        expect(fixture.state.richZoomModalScalePercent).toBe(100);

        fixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            target: (() => {
                var node = document.createElement('button');
                node.setAttribute('data-action', 'rich-zoom-scale-in');
                return node;
            })()
        });

        expect(fixture.state.richZoomModalScaleMode).toBe('manual');
        expect(fixture.state.richZoomModalScalePercent).toBe(125);

        var nextHandled = fixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            target: (() => {
                var node = document.createElement('button');
                node.setAttribute('data-action', 'rich-zoom-next');
                return node;
            })()
        });

        expect(nextHandled).toBe(true);
        expect(fixture.state.richZoomModalGalleryIndex).toBe(2);
        expect(fixture.state.richZoomModalMarkup).toContain('/c.png');
        expect(fixture.state.richZoomModalScaleMode).toBe('fit');
        expect(fixture.state.richZoomModalScalePercent).toBe(100);

        var prevHandled = fixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            target: (() => {
                var node = document.createElement('button');
                node.setAttribute('data-action', 'rich-zoom-prev');
                return node;
            })()
        });

        expect(prevHandled).toBe(true);
        expect(fixture.state.richZoomModalGalleryIndex).toBe(1);
        expect(fixture.state.richZoomModalMarkup).toContain('/b.png');
    });

    it('updates scale state for image and table zoom controls', function () {
        var imageFixture = createFixture({
            state: {
                richZoomModalOpen: true,
                richZoomModalType: 'image',
                richZoomModalTitle: 'Gambar Soal',
                richZoomModalMarkup: '<img src="/soal.png" alt="Soal" />',
                richZoomModalScaleMode: 'fit',
                richZoomModalScalePercent: 100
            }
        });

        imageFixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            target: (() => {
                var node = document.createElement('button');
                node.setAttribute('data-action', 'rich-zoom-scale-in');
                return node;
            })()
        });

        expect(imageFixture.state.richZoomModalScaleMode).toBe('manual');
        expect(imageFixture.state.richZoomModalScalePercent).toBe(125);

        imageFixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            target: (() => {
                var node = document.createElement('button');
                node.setAttribute('data-action', 'rich-zoom-scale-reset');
                return node;
            })()
        });

        expect(imageFixture.state.richZoomModalScaleMode).toBe('manual');
        expect(imageFixture.state.richZoomModalScalePercent).toBe(100);

        imageFixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            target: (() => {
                var node = document.createElement('button');
                node.setAttribute('data-action', 'rich-zoom-scale-fit');
                return node;
            })()
        });

        expect(imageFixture.state.richZoomModalScaleMode).toBe('fit');
        expect(imageFixture.state.richZoomModalScalePercent).toBe(100);

        var tableFixture = createFixture({
            state: {
                richZoomModalOpen: true,
                richZoomModalType: 'table',
                richZoomModalTitle: 'Tabel Soal',
                richZoomModalMarkup: '<div class="cbt-rich-table-wrap"><table class="cbt-rich-content-table"><tbody><tr><td>Isi</td></tr></tbody></table></div>',
                richZoomModalScaleMode: 'fit',
                richZoomModalScalePercent: 100
            }
        });

        tableFixture.manager.handleRootClick({
            preventDefault: vi.fn(),
            target: (() => {
                var node = document.createElement('button');
                node.setAttribute('data-action', 'rich-zoom-scale-out');
                return node;
            })()
        });

        expect(tableFixture.state.richZoomModalScaleMode).toBe('manual');
        expect(tableFixture.state.richZoomModalScalePercent).toBe(75);
    });

    it('uses arrow keys to navigate an open image gallery', function () {
        var fixture = createFixture({
            state: {
                richZoomModalOpen: true,
                richZoomModalType: 'image',
                richZoomModalTitle: 'Gambar Soal',
                richZoomModalMarkup: '<img src="/a.png" alt="A" />',
                richZoomModalGalleryId: 'gallery-99',
                richZoomModalGalleryIndex: 0,
                richZoomModalGalleryItems: [
                    { markup: '<img src="/a.png" alt="A" />' },
                    { markup: '<img src="/b.png" alt="B" />' }
                ],
                richZoomModalGalleryCount: 2
            }
        });

        var rightHandled = fixture.manager.handleKeydown({
            altKey: false,
            ctrlKey: false,
            key: 'ArrowRight',
            metaKey: false,
            preventDefault: vi.fn(),
            repeat: false,
            shiftKey: false
        });

        expect(rightHandled).toBe(true);
        expect(fixture.state.richZoomModalGalleryIndex).toBe(1);
        expect(fixture.state.richZoomModalMarkup).toContain('/b.png');
    });

    it('closes the rich zoom modal on Escape before other exam overlays', function () {
        var fixture = createFixture({
            state: {
                richZoomModalOpen: true,
                richZoomModalType: 'image',
                richZoomModalTitle: 'Gambar Soal',
                richZoomModalMarkup: '<img src="/soal.png" alt="Soal" />',
                richZoomModalGalleryId: 'gallery-1',
                richZoomModalGalleryIndex: 0,
                richZoomModalGalleryItems: [
                    { markup: '<img src="/soal.png" alt="Soal" />' }
                ],
                richZoomModalGalleryCount: 1,
                richZoomModalScaleMode: 'manual',
                richZoomModalScalePercent: 150
            }
        });
        var event = {
            altKey: false,
            ctrlKey: false,
            key: 'Escape',
            metaKey: false,
            preventDefault: vi.fn(),
            repeat: false,
            shiftKey: false
        };

        var handled = fixture.manager.handleKeydown(event);

        expect(handled).toBe(true);
        expect(event.preventDefault).toHaveBeenCalledTimes(1);
        expect(fixture.state.richZoomModalOpen).toBe(false);
        expect(fixture.state.richZoomModalMarkup).toBe('');
        expect(fixture.state.richZoomModalGalleryItems).toEqual([]);
        expect(fixture.state.richZoomModalGalleryCount).toBe(0);
        expect(fixture.state.richZoomModalScaleMode).toBe('fit');
        expect(fixture.state.richZoomModalScalePercent).toBe(100);
        expect(fixture.render).toHaveBeenCalledWith('escape-close-rich-zoom', {});
    });
});
