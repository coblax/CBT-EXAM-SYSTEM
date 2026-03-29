import { describe, expect, it } from 'vitest';
import { renderMathInContainer } from '../../../src/shared/math-render.js';

describe('renderMathInContainer', function () {
    it('renders inline and block equation placeholders with KaTeX', function () {
        document.body.innerHTML = [
            '<div id="fixture">',
            '<span class="cbt-math" data-cbt-math="\\frac{1}{2}" data-cbt-math-display="inline">(1)/(2)</span>',
            '<div class="cbt-math cbt-math-block" data-cbt-math="\\sqrt{9}" data-cbt-math-display="block">√(9)</div>',
            '</div>',
        ].join('');

        var root = document.getElementById('fixture');
        var renderedCount = renderMathInContainer(root);

        expect(renderedCount).toBe(2);
        expect(root.querySelectorAll('.katex').length).toBeGreaterThanOrEqual(2);
        expect(root.querySelector('.cbt-math[data-cbt-math-display="inline"]').classList.contains('is-katex-rendered')).toBe(true);
        expect(root.querySelector('.cbt-math[data-cbt-math-display="block"]').classList.contains('is-katex-rendered')).toBe(true);
    });

    it('keeps fallback content visible when KaTeX parsing fails', function () {
        document.body.innerHTML = '<div id="fixture"><span class="cbt-math" data-cbt-math="\\frac{" data-cbt-math-display="inline">(broken)</span></div>';
        var root = document.getElementById('fixture');
        var node = root.querySelector('.cbt-math');

        var renderedCount = renderMathInContainer(root);

        expect(renderedCount).toBe(0);
        expect(node.innerHTML).toBe('(broken)');
        expect(node.classList.contains('is-katex-fallback')).toBe(true);
        expect(node.querySelector('.katex')).toBeNull();
    });

    it('does not duplicate KaTeX output on repeated renders', function () {
        document.body.innerHTML = '<div id="fixture"><span class="cbt-math" data-cbt-math="x^{2}" data-cbt-math-display="inline">x^(2)</span></div>';
        var root = document.getElementById('fixture');
        var node = root.querySelector('.cbt-math');

        renderMathInContainer(root);
        var firstPassHtml = node.innerHTML;
        var firstPassKatexCount = node.querySelectorAll('.katex').length;

        renderMathInContainer(root);

        expect(node.innerHTML).toBe(firstPassHtml);
        expect(node.querySelectorAll('.katex').length).toBe(firstPassKatexCount);
    });
});
