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
        expect(html).toContain('<div class="cbt-option-label"><table>');
        expect(html).not.toContain('<span class="cbt-option-label"><table>');
    });
});
