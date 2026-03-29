import { describe, expect, it } from 'vitest';
import {
    buildEquationHtml,
    findEquationWrapperRange,
    getEquationTemplateCatalog,
    getEquationTemplateSource,
    getQuickSymbolCatalog,
    insertTokenAtCursorValue,
    parseEquationWrapperMarkup,
    renderEquationPreview,
    sanitizeTfMatrixPreviewHtml,
    suggestEquationDisplayMode,
} from '../../../src/admin/math-authoring.js';

describe('math authoring helpers', function () {
    it('builds inline and block equation wrappers with escaped fallback source', function () {
        var inlineHtml = buildEquationHtml('\\frac{a}{b}', 'inline');
        var blockHtml = buildEquationHtml('\\sqrt{x}', 'block');

        expect(inlineHtml).toContain('class="cbt-math"');
        expect(inlineHtml).toContain('data-cbt-math="\\frac{a}{b}"');
        expect(inlineHtml).toContain('data-cbt-math-display="inline"');
        expect(blockHtml).toContain('class="cbt-math cbt-math-block"');
        expect(blockHtml).toContain('data-cbt-math-display="block"');
    });

    it('parses and finds an existing wrapper range from textarea-like html', function () {
        var rawHtml = '<p>Awal</p><span class="cbt-math" data-cbt-math="x^{2}" data-cbt-math-display="inline">x^{2}</span><p>Akhir</p>';
        var parsed = parseEquationWrapperMarkup('<span class="cbt-math" data-cbt-math="x^{2}" data-cbt-math-display="inline">x^{2}</span>');
        var range = findEquationWrapperRange(rawHtml, 18, 18);

        expect(parsed).toEqual({
            source: 'x^{2}',
            displayMode: 'inline',
            tagName: 'span',
            markup: '<span class="cbt-math" data-cbt-math="x^{2}" data-cbt-math-display="inline">x^{2}</span>',
        });
        expect(range && range.source).toBe('x^{2}');
        expect(range && range.displayMode).toBe('inline');
        expect(range && range.start).toBeGreaterThanOrEqual(0);
        expect(range && range.end).toBeGreaterThan(range && range.start);
    });

    it('returns quick template catalog, legacy template keys, and quick symbols for equation v2', function () {
        var catalog = getEquationTemplateCatalog();
        var symbols = getQuickSymbolCatalog();

        expect(catalog.map((entry) => entry.key)).toEqual([
            'basic',
            'calculus',
            'linear-algebra',
            'statistics',
        ]);
        expect(catalog[0].templates[0].key).toBe('fraction');
        expect(catalog[1].templates.some((entry) => entry.key === 'limit')).toBe(true);
        expect(catalog[2].templates.some((entry) => entry.key === 'vector-2d')).toBe(true);
        expect(catalog[3].templates.some((entry) => entry.key === 'conditional-probability')).toBe(true);
        expect(getEquationTemplateSource('fraction')).toBe('\\frac{a}{b}');
        expect(getEquationTemplateSource('radical')).toBe('\\sqrt{x}');
        expect(getEquationTemplateSource('integral')).toBe('\\int_a^b f(x)\\,dx');
        expect(getEquationTemplateSource('matrix')).toBe('\\begin{bmatrix} a & b \\\\ c & d \\end{bmatrix}');
        expect(getEquationTemplateSource('limit')).toBe('\\lim_{x \\to a} f(x)');
        expect(getEquationTemplateSource('mean')).toBe('\\bar{x} = \\frac{\\sum x}{n}');
        expect(symbols.some((entry) => entry.key === 'theta' && entry.token === '\\theta')).toBe(true);
        expect(symbols.some((entry) => entry.key === 'sum' && entry.token === '\\sum')).toBe(true);
    });

    it('suggests display mode and inserts symbol tokens at caret without replacing unrelated source', function () {
        var inserted = insertTokenAtCursorValue('x^{2} + ', 8, 8, '\\theta');

        expect(inserted).toEqual({
            value: 'x^{2} + \\theta',
            selectionStart: 14,
            selectionEnd: 14,
        });
        expect(suggestEquationDisplayMode('x^{2}')).toBe('inline');
        expect(suggestEquationDisplayMode('\\frac{a}{b}')).toBe('block');
        expect(suggestEquationDisplayMode('\\begin{bmatrix} a & b \\\\ c & d \\end{bmatrix}')).toBe('block');
        expect(suggestEquationDisplayMode('P(A \\mid B) = \\frac{P(A \\cap B)}{P(B)}')).toBe('block');
    });

    it('keeps only text and math wrapper markup for TF Matrix preview', function () {
        var previewHtml = sanitizeTfMatrixPreviewHtml(
            'Cocokkan <strong>nilai</strong> berikut.\n<span class="cbt-math" data-cbt-math="x^{2}" data-cbt-math-display="inline">x^{2}</span>'
        );

        expect(previewHtml).toContain('Cocokkan &lt;strong&gt;nilai&lt;/strong&gt; berikut.');
        expect(previewHtml).toContain('data-cbt-math="x^{2}"');
        expect(previewHtml).toContain('<br />');
        expect(previewHtml).not.toContain('<strong>');
    });

    it('marks preview invalid when KaTeX source cannot be rendered', function () {
        document.body.innerHTML = '<div id="fixture"></div>';
        var previewNode = document.getElementById('fixture');

        var invalidResult = renderEquationPreview(previewNode, '\\frac{', 'inline');
        var validResult = renderEquationPreview(previewNode, '\\sqrt{9}', 'block');

        expect(invalidResult.valid).toBe(false);
        expect(previewNode.querySelector('.cbt-math')).not.toBeNull();
        expect(validResult.valid).toBe(true);
        expect(previewNode.querySelector('.katex')).not.toBeNull();
    });
});
