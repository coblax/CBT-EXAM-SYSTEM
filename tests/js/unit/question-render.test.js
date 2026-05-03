import { describe, expect, it } from 'vitest';
import { createQuestionRenderManager } from '../../../src/frontend/app/exam/question-render.js';

function createManager(overrides) {
    overrides = overrides || {};

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
        resolveStoredAnswerValueForQuestion: overrides.resolveStoredAnswerValueForQuestion || function () {
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

    it('renders ordering items with rich content and up down controls', function () {
        var manager = createManager();
        var html = manager.renderQuestionInput({
            id: 42,
            question_type: 'ordering',
            options: [
                {
                    id: 301,
                    option_key: 'A',
                    option_text: '<table><tbody><tr><td>Langkah 1</td></tr></tbody></table>'
                },
                {
                    id: 302,
                    option_key: 'B',
                    option_text: '<p>Langkah 2</p>'
                }
            ]
        });

        expect(html).toContain('class="cbt-ordering"');
        expect(html).toContain('data-cbt-ordering-list="1"');
        expect(html).toContain('data-action="answer-ordering-move"');
        expect(html).toContain('data-direction="up"');
        expect(html).toContain('data-direction="down"');
        expect(html).toContain('<div class="rendered-option"><table>');
    });

    it('restores selected matching dropdown answers', function () {
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function () {
                return { 1: 202 };
            }
        });
        var html = manager.renderQuestionInput({
            id: 51,
            question_type: 'matching',
            matching_meta: {
                items: [
                    {
                        key: '1',
                        text: 'Ibukota Jepang'
                    }
                ]
            },
            options: [
                {
                    id: 201,
                    option_key: 'A',
                    option_text: 'Seoul'
                },
                {
                    id: 202,
                    option_key: 'B',
                    option_text: 'Tokyo'
                }
            ]
        });

        expect(html).toContain('data-action="answer-matching"');
        expect(html).toContain('data-matching-key="1"');
        expect(html).toContain('<option value="202" selected>Tokyo</option>');
    });

    it('restores selected cloze dropdown answers inside placeholders', function () {
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function () {
                return { 1: 302 };
            }
        });
        var html = manager.renderQuestionStem({
            id: 52,
            question_type: 'cloze_dropdown',
            question_text: 'Ibu kota Jepang adalah [DROPDOWN_1].',
            cloze_dropdown_meta: {
                blanks: [
                    {
                        key: '1',
                        position: 1,
                        options: [
                            {
                                id: 301,
                                option_key: 'A',
                                option_text: 'Seoul'
                            },
                            {
                                id: 302,
                                option_key: 'B',
                                option_text: 'Tokyo'
                            }
                        ]
                    }
                ]
            }
        });

        expect(html).toContain('data-action="answer-cloze-dropdown"');
        expect(html).toContain('data-cloze-key="1"');
        expect(html).toContain('<option value="302" selected>Tokyo</option>');
    });
});
