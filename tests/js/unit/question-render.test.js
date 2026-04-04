import { describe, expect, it } from 'vitest';
import { createQuestionRenderManager } from '../../../src/frontend/app/exam/question-render.js';

function createManager() {
    return createQuestionRenderManager({
        escapeHtml: function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        resolveStoredAnswerValueForQuestion: function () {
            return null;
        },
        renderExamRichHtml: function (value, options) {
            return '<div class="rendered-' + String(options && options.context || 'question') + '">' + String(value || '') + '<button data-action="open-rich-zoom" type="button">Perbesar</button></div>';
        },
        safeRichHtml: function (value) {
            return String(value || '');
        }
    });
}

describe('createQuestionRenderManager', function () {
    it('renders choice option content inside block containers so rich tables stay valid', function () {
        var manager = createManager();
        var html = manager.renderQuestionInput({
            id: 12,
            question_type: 'multiple_choice',
            options: [
                {
                    id: 101,
                    option_key: 'A',
                    option_text: '<table><tbody><tr><td>Isi</td></tr></tbody></table>'
                }
            ]
        });

        expect(html).toContain('<div class="cbt-option-row">');
        expect(html).toContain('<div class="cbt-option-label"><div class="rendered-option"><table>');
        expect(html).not.toContain('<span class="cbt-option-label"><table>');
    });

    it('uses exam rich renderer for choice options so zoom controls can be injected', function () {
        var manager = createManager();
        var html = manager.renderQuestionInput({
            id: 12,
            question_type: 'multiple_choice',
            options: [
                {
                    id: 101,
                    option_key: 'A',
                    option_text: '<img src="/diagram.png" alt="Diagram" />'
                }
            ]
        });

        expect(html).toContain('rendered-option');
        expect(html).toContain('data-action="open-rich-zoom"');
    });
});
