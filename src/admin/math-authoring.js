import { renderMathInContainer } from '../shared/math-render';
import './math-authoring.css';

var MANUAL_RICH_EDITOR_ID_PATTERN = /^(cbt_question_text_editor|cbt_essay_answer_editor|cbt_question_explanation_editor|cbt_(mc|ma)_option_\d+|cbt_ordering_item_\d+|cbt_matching_(left|right)_\d+|cbt-tfm-statement-\d+)$/;
var EQUATION_MODAL_ID = 'cbt-admin-equation-modal';
var EQUATION_INSERT_BUTTON_ID = 'cbt-admin-equation-apply';
var EQUATION_SOURCE_ID = 'cbt-admin-equation-source';
var EQUATION_PREVIEW_ID = 'cbt-admin-equation-preview';
var EQUATION_ERROR_ID = 'cbt-admin-equation-error';
var EQUATION_TITLE_ID = 'cbt-admin-equation-title';
var EQUATION_TEMPLATE_LIST_ID = 'cbt-admin-equation-template-list';
var EQUATION_STATUS_ID = 'cbt-admin-equation-status';
var EQUATION_CURRENT_DISPLAY_ID = 'cbt-admin-equation-current-display';
var EQUATION_SUGGESTED_DISPLAY_ID = 'cbt-admin-equation-suggested-display';
var EQUATION_USE_SUGGESTION_ID = 'cbt-admin-equation-use-suggestion';

var EQUATION_TEMPLATE_CATALOG = [
    {
        key: 'basic',
        label: 'Dasar',
        templates: [
            { key: 'fraction', label: 'Pecahan', source: '\\frac{a}{b}' },
            { key: 'radical', label: 'Akar', source: '\\sqrt{x}' },
            { key: 'power', label: 'Pangkat', source: 'x^{n}' },
            { key: 'subscript', label: 'Indeks', source: 'x_{n}' },
            { key: 'logarithm', label: 'Logaritma', source: '\\log_a(x)' },
        ],
    },
    {
        key: 'calculus',
        label: 'Kalkulus',
        templates: [
            { key: 'limit', label: 'Limit', source: '\\lim_{x \\to a} f(x)' },
            { key: 'derivative', label: 'Turunan', source: '\\frac{d}{dx} f(x)' },
            { key: 'integral-basic', label: 'Integral', source: '\\int f(x)\\,dx' },
            { key: 'integral', label: 'Integral Batas', source: '\\int_a^b f(x)\\,dx' },
        ],
    },
    {
        key: 'linear-algebra',
        label: 'Aljabar Linear',
        templates: [
            { key: 'matrix', label: 'Matriks 2x2', source: '\\begin{bmatrix} a & b \\\\ c & d \\end{bmatrix}' },
            { key: 'matrix-3x3', label: 'Matriks 3x3', source: '\\begin{bmatrix} a & b & c \\\\ d & e & f \\\\ g & h & i \\end{bmatrix}' },
            { key: 'vector-2d', label: 'Vektor 2D', source: '\\vec{v} = \\begin{bmatrix} x \\\\ y \\end{bmatrix}' },
        ],
    },
    {
        key: 'statistics',
        label: 'Statistik',
        templates: [
            { key: 'mean', label: 'Rata-rata', source: '\\bar{x} = \\frac{\\sum x}{n}' },
            { key: 'variance', label: 'Varians', source: 's^2 = \\frac{\\sum (x-\\bar{x})^2}{n-1}' },
            { key: 'conditional-probability', label: 'Probabilitas Bersyarat', source: 'P(A \\mid B) = \\frac{P(A \\cap B)}{P(B)}' },
        ],
    },
];

var QUICK_SYMBOL_CATALOG = [
    { key: 'pi', label: 'π', token: '\\pi' },
    { key: 'theta', label: 'θ', token: '\\theta' },
    { key: 'alpha', label: 'α', token: '\\alpha' },
    { key: 'beta', label: 'β', token: '\\beta' },
    { key: 'infinity', label: '∞', token: '\\infty' },
    { key: 'plus-minus', label: '±', token: '\\pm' },
    { key: 'not-equal', label: '≠', token: '\\neq' },
    { key: 'less-equal', label: '≤', token: '\\le' },
    { key: 'greater-equal', label: '≥', token: '\\ge' },
    { key: 'approx', label: '≈', token: '\\approx' },
    { key: 'times', label: '×', token: '\\times' },
    { key: 'dot', label: '·', token: '\\cdot' },
    { key: 'arrow-right', label: '→', token: '\\rightarrow' },
    { key: 'arrow-left', label: '←', token: '\\leftarrow' },
    { key: 'sum', label: 'Σ', token: '\\sum' },
    { key: 'integral-sign', label: '∫', token: '\\int' },
];

var authoringState = {
    targetKind: '',
    targetId: '',
    editorId: '',
    mode: 'insert',
    source: '',
    displayMode: 'inline',
    activeCategory: 'basic',
    wrapperRange: null,
    wrapperNode: null,
};

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function cloneTemplateCatalog() {
    return EQUATION_TEMPLATE_CATALOG.map(function (category) {
        return {
            key: category.key,
            label: category.label,
            templates: category.templates.map(function (template) {
                return {
                    key: template.key,
                    label: template.label,
                    source: template.source,
                };
            }),
        };
    });
}

function cloneSymbolCatalog() {
    return QUICK_SYMBOL_CATALOG.map(function (symbol) {
        return {
            key: symbol.key,
            label: symbol.label,
            token: symbol.token,
        };
    });
}

export function normalizeEquationDisplayMode(value) {
    return String(value || '').toLowerCase() === 'block' ? 'block' : 'inline';
}

export function getEquationTemplateCatalog() {
    return cloneTemplateCatalog();
}

export function getQuickSymbolCatalog() {
    return cloneSymbolCatalog();
}

function findTemplateEntry(templateKey) {
    var normalizedKey = String(templateKey || '').trim().toLowerCase();
    if (normalizedKey === '') {
        return null;
    }

    for (var categoryIndex = 0; categoryIndex < EQUATION_TEMPLATE_CATALOG.length; categoryIndex += 1) {
        var category = EQUATION_TEMPLATE_CATALOG[categoryIndex];
        for (var templateIndex = 0; templateIndex < category.templates.length; templateIndex += 1) {
            var template = category.templates[templateIndex];
            if (String(template.key || '').trim().toLowerCase() === normalizedKey) {
                return {
                    categoryKey: category.key,
                    template: template,
                };
            }
        }
    }

    return null;
}

function findCategoryEntry(categoryKey) {
    var normalizedKey = String(categoryKey || '').trim().toLowerCase();
    if (normalizedKey === '') {
        return EQUATION_TEMPLATE_CATALOG[0] || null;
    }

    for (var index = 0; index < EQUATION_TEMPLATE_CATALOG.length; index += 1) {
        var category = EQUATION_TEMPLATE_CATALOG[index];
        if (String(category.key || '').trim().toLowerCase() === normalizedKey) {
            return category;
        }
    }

    return EQUATION_TEMPLATE_CATALOG[0] || null;
}

function findTemplateCategoryKeyBySource(source) {
    var normalizedSource = String(source || '').trim();
    if (normalizedSource === '') {
        return '';
    }

    for (var categoryIndex = 0; categoryIndex < EQUATION_TEMPLATE_CATALOG.length; categoryIndex += 1) {
        var category = EQUATION_TEMPLATE_CATALOG[categoryIndex];
        for (var templateIndex = 0; templateIndex < category.templates.length; templateIndex += 1) {
            var template = category.templates[templateIndex];
            if (String(template.source || '').trim() === normalizedSource) {
                return category.key;
            }
        }
    }

    return '';
}

function findQuickSymbolEntry(symbolKey) {
    var normalizedKey = String(symbolKey || '').trim().toLowerCase();
    if (normalizedKey === '') {
        return null;
    }

    for (var index = 0; index < QUICK_SYMBOL_CATALOG.length; index += 1) {
        var symbol = QUICK_SYMBOL_CATALOG[index];
        if (String(symbol.key || '').trim().toLowerCase() === normalizedKey) {
            return symbol;
        }
    }

    return null;
}

export function getEquationTemplateSource(templateKey) {
    var templateEntry = findTemplateEntry(templateKey);
    return templateEntry ? String(templateEntry.template.source || '') : '';
}

export function suggestEquationDisplayMode(source) {
    var normalizedSource = String(source || '').trim();
    if (normalizedSource === '') {
        return 'inline';
    }

    var blockIndicators = [
        '\\frac',
        '\\dfrac',
        '\\sum',
        '\\prod',
        '\\int',
        '\\begin{',
        '\\\\',
    ];

    for (var index = 0; index < blockIndicators.length; index += 1) {
        if (normalizedSource.indexOf(blockIndicators[index]) !== -1) {
            return 'block';
        }
    }

    if (normalizedSource.length > 24) {
        return 'block';
    }

    return 'inline';
}

export function insertTokenAtCursorValue(currentValue, selectionStart, selectionEnd, token) {
    var rawValue = String(currentValue || '');
    var start = Math.max(0, Number(selectionStart) || 0);
    var end = Math.max(start, Number(selectionEnd) || start);
    var insertValue = String(token || '');
    var nextValue = rawValue.slice(0, start) + insertValue + rawValue.slice(end);
    var nextCursor = start + insertValue.length;

    return {
        value: nextValue,
        selectionStart: nextCursor,
        selectionEnd: nextCursor,
    };
}

export function buildEquationHtml(source, displayMode) {
    var normalizedSource = String(source || '').trim();
    if (normalizedSource === '') {
        return '';
    }

    var normalizedDisplayMode = normalizeEquationDisplayMode(displayMode);
    var tagName = normalizedDisplayMode === 'block' ? 'div' : 'span';
    var className = normalizedDisplayMode === 'block' ? 'cbt-math cbt-math-block' : 'cbt-math';

    return [
        '<' + tagName + ' class="' + className + '" data-cbt-math="' + escapeHtml(normalizedSource) + '" data-cbt-math-display="' + normalizedDisplayMode + '">',
        escapeHtml(normalizedSource),
        '</' + tagName + '>',
    ].join('');
}

export function parseEquationWrapperMarkup(markup) {
    if (typeof document === 'undefined') {
        return null;
    }

    var probe = document.createElement('div');
    probe.innerHTML = String(markup || '').trim();
    if (!probe.firstElementChild || probe.children.length !== 1) {
        return null;
    }

    var node = probe.firstElementChild;
    if (!(node instanceof HTMLElement) || !node.matches('.cbt-math[data-cbt-math]')) {
        return null;
    }

    return {
        source: String(node.getAttribute('data-cbt-math') || '').trim(),
        displayMode: normalizeEquationDisplayMode(node.getAttribute('data-cbt-math-display')),
        tagName: node.tagName.toLowerCase(),
        markup: String(markup || '').trim(),
    };
}

export function findEquationWrapperRange(html, selectionStart, selectionEnd) {
    var raw = String(html || '');
    if (raw === '') {
        return null;
    }

    var start = Math.max(0, Number(selectionStart) || 0);
    var end = Math.max(start, Number(selectionEnd) || start);
    var pattern = /<(span|div)\b[\s\S]*?<\/\1>/gi;
    var match = pattern.exec(raw);
    while (match) {
        var markup = String(match[0] || '');
        var parsed = parseEquationWrapperMarkup(markup);
        if (parsed) {
            var matchStart = Number(match.index) || 0;
            var matchEnd = matchStart + markup.length;
            var overlaps = (
                (start >= matchStart && start <= matchEnd) ||
                (end >= matchStart && end <= matchEnd) ||
                (start <= matchStart && end >= matchEnd)
            );
            if (overlaps) {
                return {
                    start: matchStart,
                    end: matchEnd,
                    source: parsed.source,
                    displayMode: parsed.displayMode,
                    markup: markup,
                };
            }
        }
        match = pattern.exec(raw);
    }

    return null;
}

export function sanitizeTfMatrixPreviewHtml(rawValue) {
    var raw = String(rawValue || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    if (raw === '') {
        return '';
    }

    var fragments = [];
    var cursor = 0;
    var pattern = /<(span|div)\b[\s\S]*?<\/\1>/gi;
    var match = pattern.exec(raw);
    while (match) {
        var matchMarkup = String(match[0] || '');
        var matchStart = Number(match.index) || 0;
        if (matchStart > cursor) {
            fragments.push(
                escapeHtml(raw.slice(cursor, matchStart)).replace(/\n/g, '<br />')
            );
        }

        var parsed = parseEquationWrapperMarkup(matchMarkup);
        if (parsed) {
            fragments.push(buildEquationHtml(parsed.source, parsed.displayMode));
        } else {
            fragments.push(escapeHtml(matchMarkup).replace(/\n/g, '<br />'));
        }

        cursor = matchStart + matchMarkup.length;
        match = pattern.exec(raw);
    }

    if (cursor < raw.length) {
        fragments.push(escapeHtml(raw.slice(cursor)).replace(/\n/g, '<br />'));
    }

    return fragments.join('');
}

export function renderEquationPreview(previewNode, source, displayMode) {
    if (!(previewNode instanceof HTMLElement)) {
        return { valid: false, html: '' };
    }

    var wrapperHtml = buildEquationHtml(source, displayMode);
    previewNode.innerHTML = wrapperHtml;
    if (wrapperHtml === '') {
        return { valid: false, html: '' };
    }

    renderMathInContainer(previewNode);
    var mathNode = previewNode.querySelector('.cbt-math[data-cbt-math]');
    var isValid = !!(mathNode && (
        mathNode.classList.contains('is-katex-rendered') ||
        mathNode.querySelector('.katex')
    ));

    return {
        valid: isValid,
        html: wrapperHtml,
    };
}

function matchesManualRichEditorId(editorId) {
    return MANUAL_RICH_EDITOR_ID_PATTERN.test(String(editorId || ''));
}

function getTinyMceGlobal() {
    return window.tinymce || window.tinyMCE || null;
}

function getTinyMceEditor(editorId) {
    var tinyMceGlobal = getTinyMceGlobal();
    if (!tinyMceGlobal || typeof tinyMceGlobal.get !== 'function') {
        return null;
    }
    return tinyMceGlobal.get(String(editorId || '')) || null;
}

function findMathWrapperElement(node) {
    var current = node;
    while (current && current instanceof HTMLElement) {
        if (current.matches('.cbt-math[data-cbt-math]')) {
            return current;
        }
        current = current.parentElement;
    }
    return null;
}

function renderCategoryButtonsMarkup() {
    return EQUATION_TEMPLATE_CATALOG.map(function (category) {
        return [
            '<button type="button" class="button button-secondary cbt-admin-equation-modal__category-button" data-cbt-equation-category="',
            escapeHtml(category.key),
            '">',
            escapeHtml(category.label),
            '</button>',
        ].join('');
    }).join('');
}

function renderQuickSymbolsMarkup() {
    return QUICK_SYMBOL_CATALOG.map(function (symbol) {
        return [
            '<button type="button" class="button button-secondary cbt-admin-equation-modal__symbol-button" data-cbt-equation-symbol="',
            escapeHtml(symbol.key),
            '" title="',
            escapeHtml(symbol.token),
            '">',
            escapeHtml(symbol.label),
            '</button>',
        ].join('');
    }).join('');
}

function createEquationModal() {
    var existing = document.getElementById(EQUATION_MODAL_ID);
    if (existing instanceof HTMLElement) {
        return existing;
    }

    var modal = document.createElement('div');
    modal.id = EQUATION_MODAL_ID;
    modal.className = 'cbt-admin-equation-modal';
    modal.hidden = true;
    modal.innerHTML = [
        '<div class="cbt-admin-equation-modal__backdrop" data-cbt-equation-close="1"></div>',
        '<div class="cbt-admin-equation-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="' + EQUATION_TITLE_ID + '">',
        '<div class="cbt-admin-equation-modal__header">',
        '<div>',
        '<h2 id="' + EQUATION_TITLE_ID + '">Insert Equation</h2>',
        '<p>Pilih kategori template, susun source LaTeX, lalu sisipkan wrapper math ke editor aktif.</p>',
        '</div>',
        '<button type="button" class="button button-secondary" data-cbt-equation-close="1">Tutup</button>',
        '</div>',
        '<div class="cbt-admin-equation-modal__body">',
        '<section class="cbt-admin-equation-modal__panel cbt-admin-equation-modal__panel--catalog">',
        '<div class="cbt-admin-equation-modal__panel-header">',
        '<span class="cbt-admin-equation-modal__templates-label">Kategori Template</span>',
        '<p>Pilih kategori lalu klik template untuk mengganti seluruh source.</p>',
        '</div>',
        '<div class="cbt-admin-equation-modal__category-list">',
        renderCategoryButtonsMarkup(),
        '</div>',
        '<div id="' + EQUATION_TEMPLATE_LIST_ID + '" class="cbt-admin-equation-modal__template-list"></div>',
        '</section>',
        '<section class="cbt-admin-equation-modal__panel cbt-admin-equation-modal__panel--source">',
        '<div class="cbt-admin-equation-modal__panel-header">',
        '<span class="cbt-admin-equation-modal__templates-label">LaTeX Source</span>',
        '<p>Template mengganti source. Simbol cepat menambah token di posisi caret.</p>',
        '</div>',
        '<label class="cbt-admin-equation-modal__field">',
        '<span>Source</span>',
        '<textarea id="' + EQUATION_SOURCE_ID + '" class="large-text code" rows="8" spellcheck="false" placeholder="Contoh: \\\\frac{a}{b}"></textarea>',
        '</label>',
        '<div class="cbt-admin-equation-modal__symbols">',
        '<span class="cbt-admin-equation-modal__templates-label">Simbol Cepat</span>',
        '<div class="cbt-admin-equation-modal__symbol-list">',
        renderQuickSymbolsMarkup(),
        '</div>',
        '</div>',
        '<fieldset class="cbt-admin-equation-modal__display">',
        '<legend>Mode Tampilan</legend>',
        '<label><input type="radio" name="cbt-admin-equation-display" value="inline" checked /> Inline</label>',
        '<label><input type="radio" name="cbt-admin-equation-display" value="block" /> Block</label>',
        '</fieldset>',
        '</section>',
        '<section class="cbt-admin-equation-modal__panel cbt-admin-equation-modal__panel--preview">',
        '<div class="cbt-admin-equation-modal__panel-header">',
        '<span class="cbt-admin-equation-modal__templates-label">Preview & Hint</span>',
        '<p>Suggestion hanya membantu. Admin tetap memilih inline atau block secara manual.</p>',
        '</div>',
        '<div class="cbt-admin-equation-modal__summary">',
        '<div class="cbt-admin-equation-modal__summary-item">',
        '<span>Status</span>',
        '<strong id="' + EQUATION_STATUS_ID + '">Belum ada source</strong>',
        '</div>',
        '<div class="cbt-admin-equation-modal__summary-item">',
        '<span>Display Aktif</span>',
        '<strong id="' + EQUATION_CURRENT_DISPLAY_ID + '">inline</strong>',
        '</div>',
        '<div class="cbt-admin-equation-modal__summary-item">',
        '<span>Saran Display</span>',
        '<div class="cbt-admin-equation-modal__suggestion-row">',
        '<strong id="' + EQUATION_SUGGESTED_DISPLAY_ID + '">inline</strong>',
        '<button type="button" class="button button-secondary button-small" id="' + EQUATION_USE_SUGGESTION_ID + '" data-cbt-equation-use-suggestion="1">Gunakan Saran</button>',
        '</div>',
        '</div>',
        '</div>',
        '<div class="cbt-admin-equation-modal__preview-wrap">',
        '<strong>Live Preview</strong>',
        '<div id="' + EQUATION_PREVIEW_ID + '" class="cbt-admin-equation-modal__preview"></div>',
        '<p id="' + EQUATION_ERROR_ID + '" class="cbt-admin-equation-modal__error" hidden>Persamaan KaTeX tidak valid. Perbaiki source sebelum menyisipkan ke editor.</p>',
        '</div>',
        '</section>',
        '</div>',
        '<div class="cbt-admin-equation-modal__footer">',
        '<button type="button" class="button button-secondary" data-cbt-equation-close="1">Batal</button>',
        '<button type="button" class="button button-primary" id="' + EQUATION_INSERT_BUTTON_ID + '" disabled>Insert</button>',
        '</div>',
        '</div>',
    ].join('');
    document.body.appendChild(modal);
    return modal;
}

function getEquationModalElements() {
    var modal = createEquationModal();
    return {
        modal: modal,
        sourceField: modal.querySelector('#' + EQUATION_SOURCE_ID),
        preview: modal.querySelector('#' + EQUATION_PREVIEW_ID),
        errorText: modal.querySelector('#' + EQUATION_ERROR_ID),
        applyButton: modal.querySelector('#' + EQUATION_INSERT_BUTTON_ID),
        title: modal.querySelector('#' + EQUATION_TITLE_ID),
        templateList: modal.querySelector('#' + EQUATION_TEMPLATE_LIST_ID),
        statusText: modal.querySelector('#' + EQUATION_STATUS_ID),
        currentDisplayText: modal.querySelector('#' + EQUATION_CURRENT_DISPLAY_ID),
        suggestedDisplayText: modal.querySelector('#' + EQUATION_SUGGESTED_DISPLAY_ID),
        useSuggestionButton: modal.querySelector('#' + EQUATION_USE_SUGGESTION_ID),
    };
}

function renderTemplateList() {
    var refs = getEquationModalElements();
    if (!(refs.templateList instanceof HTMLElement)) {
        return;
    }

    var activeCategory = findCategoryEntry(authoringState.activeCategory);
    if (!activeCategory) {
        refs.templateList.innerHTML = '';
        return;
    }

    refs.templateList.innerHTML = activeCategory.templates.map(function (template) {
        return [
            '<button type="button" class="cbt-admin-equation-modal__template-card" data-cbt-equation-template="',
            escapeHtml(template.key),
            '">',
            '<strong>',
            escapeHtml(template.label),
            '</strong>',
            '<code>',
            escapeHtml(template.source),
            '</code>',
            '</button>',
        ].join('');
    }).join('');

    Array.from(refs.modal.querySelectorAll('[data-cbt-equation-category]')).forEach(function (button) {
        if (!(button instanceof HTMLElement)) {
            return;
        }
        var isActive = String(button.getAttribute('data-cbt-equation-category') || '') === String(activeCategory.key || '');
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function setModalStatus(status, isValid) {
    var refs = getEquationModalElements();
    if (!(refs.statusText instanceof HTMLElement)) {
        return;
    }

    refs.statusText.textContent = status;
    refs.statusText.classList.toggle('is-valid', !!isValid);
    refs.statusText.classList.toggle('is-invalid', status === 'Tidak valid');
    refs.statusText.classList.toggle('is-empty', status === 'Belum ada source');
}

function updateModalPreview() {
    var refs = getEquationModalElements();
    if (!(refs.sourceField instanceof HTMLTextAreaElement)) {
        return;
    }

    authoringState.source = String(refs.sourceField.value || '');
    var normalizedSource = authoringState.source.trim();
    var checkedDisplay = refs.modal.querySelector('input[name="cbt-admin-equation-display"]:checked');
    authoringState.displayMode = normalizeEquationDisplayMode(
        checkedDisplay instanceof HTMLInputElement ? checkedDisplay.value : authoringState.displayMode
    );

    var result = renderEquationPreview(refs.preview, normalizedSource, authoringState.displayMode);
    var isValid = !!result.valid;
    var suggestedDisplay = suggestEquationDisplayMode(normalizedSource);

    refs.applyButton.disabled = !isValid;
    refs.errorText.hidden = isValid || normalizedSource === '';
    refs.applyButton.textContent = authoringState.mode === 'edit' ? 'Update' : 'Insert';
    refs.title.textContent = authoringState.mode === 'edit' ? 'Update Equation' : 'Insert Equation';

    if (normalizedSource === '') {
        setModalStatus('Belum ada source', false);
    } else if (isValid) {
        setModalStatus('Valid', true);
    } else {
        setModalStatus('Tidak valid', false);
    }

    if (refs.currentDisplayText instanceof HTMLElement) {
        refs.currentDisplayText.textContent = authoringState.displayMode;
    }
    if (refs.suggestedDisplayText instanceof HTMLElement) {
        refs.suggestedDisplayText.textContent = suggestedDisplay;
    }
    if (refs.useSuggestionButton instanceof HTMLButtonElement) {
        refs.useSuggestionButton.hidden = normalizedSource === '';
        refs.useSuggestionButton.disabled = normalizedSource === '' || suggestedDisplay === authoringState.displayMode;
        refs.useSuggestionButton.dataset.cbtEquationSuggestedDisplay = suggestedDisplay;
    }
}

function openEquationModal(nextState) {
    var refs = getEquationModalElements();
    var incomingState = nextState || {};
    authoringState = Object.assign({}, authoringState, incomingState);
    authoringState.activeCategory = String(incomingState.activeCategory || findTemplateCategoryKeyBySource(authoringState.source) || authoringState.activeCategory || 'basic');

    refs.modal.hidden = false;
    refs.sourceField.value = authoringState.source || '';

    Array.from(refs.modal.querySelectorAll('input[name="cbt-admin-equation-display"]')).forEach(function (radio) {
        if (!(radio instanceof HTMLInputElement)) {
            return;
        }
        radio.checked = normalizeEquationDisplayMode(radio.value) === normalizeEquationDisplayMode(authoringState.displayMode);
    });

    renderTemplateList();
    updateModalPreview();
    refs.sourceField.focus();
    refs.sourceField.setSelectionRange(refs.sourceField.value.length, refs.sourceField.value.length);
}

function closeEquationModal() {
    var refs = getEquationModalElements();
    refs.modal.hidden = true;
    authoringState = {
        targetKind: '',
        targetId: '',
        editorId: '',
        mode: 'insert',
        source: '',
        displayMode: 'inline',
        activeCategory: 'basic',
        wrapperRange: null,
        wrapperNode: null,
    };
}

function replaceTextareaSelection(textarea, replacementHtml, existingRange) {
    var currentValue = String(textarea.value || '');
    var selectionStart = Number(textarea.selectionStart) || 0;
    var selectionEnd = Number(textarea.selectionEnd) || selectionStart;
    var range = existingRange || findEquationWrapperRange(currentValue, selectionStart, selectionEnd);
    var start = range ? range.start : selectionStart;
    var end = range ? range.end : selectionEnd;
    var nextValue = currentValue.slice(0, start) + replacementHtml + currentValue.slice(end);
    textarea.value = nextValue;
    var nextCursor = start + replacementHtml.length;
    textarea.focus();
    textarea.setSelectionRange(nextCursor, nextCursor);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
}

function insertTokenIntoSourceField(token) {
    var refs = getEquationModalElements();
    if (!(refs.sourceField instanceof HTMLTextAreaElement)) {
        return;
    }

    var nextValue = insertTokenAtCursorValue(
        refs.sourceField.value,
        refs.sourceField.selectionStart,
        refs.sourceField.selectionEnd,
        token
    );

    refs.sourceField.value = nextValue.value;
    refs.sourceField.focus();
    refs.sourceField.setSelectionRange(nextValue.selectionStart, nextValue.selectionEnd);
    updateModalPreview();
}

function applyEquationToTarget() {
    var html = buildEquationHtml(authoringState.source, authoringState.displayMode);
    if (html === '') {
        return;
    }

    if (authoringState.targetKind === 'tinymce') {
        var editor = getTinyMceEditor(authoringState.editorId);
        if (!editor) {
            return;
        }

        editor.focus();
        if (authoringState.wrapperNode && editor.dom && typeof editor.dom.setOuterHTML === 'function') {
            editor.dom.setOuterHTML(authoringState.wrapperNode, html);
        } else if (typeof editor.insertContent === 'function') {
            editor.insertContent(html);
        } else if (typeof editor.execCommand === 'function') {
            editor.execCommand('mceInsertContent', false, html);
        }

        if (typeof editor.save === 'function') {
            editor.save();
        }
        closeEquationModal();
        return;
    }

    if (authoringState.targetKind === 'textarea') {
        var textarea = document.getElementById(authoringState.targetId);
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        replaceTextareaSelection(textarea, html, authoringState.wrapperRange);
        closeEquationModal();
    }
}

function openForTextarea(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    var range = findEquationWrapperRange(textarea.value, textarea.selectionStart, textarea.selectionEnd);
    openEquationModal({
        targetKind: 'textarea',
        targetId: textarea.id,
        editorId: textarea.id,
        mode: range ? 'edit' : 'insert',
        source: range ? range.source : '',
        displayMode: range ? range.displayMode : 'inline',
        activeCategory: range ? findTemplateCategoryKeyBySource(range.source) : 'basic',
        wrapperRange: range,
        wrapperNode: null,
    });
}

function openForEditor(editorId, preferredMode) {
    var editorWrap = document.getElementById('wp-' + String(editorId || '') + '-wrap');
    var useTextMode = String(preferredMode || '').toLowerCase() === 'text'
        || !!(editorWrap && editorWrap.classList.contains('html-active'));

    if (!useTextMode) {
        var editor = getTinyMceEditor(editorId);
        if (editor && editor.selection) {
            var selectedNode = findMathWrapperElement(editor.selection.getNode());
            var selectedSource = selectedNode ? String(selectedNode.getAttribute('data-cbt-math') || '').trim() : '';
            openEquationModal({
                targetKind: 'tinymce',
                targetId: String(editorId || ''),
                editorId: String(editorId || ''),
                mode: selectedNode ? 'edit' : 'insert',
                source: selectedSource,
                displayMode: selectedNode ? normalizeEquationDisplayMode(selectedNode.getAttribute('data-cbt-math-display')) : 'inline',
                activeCategory: findTemplateCategoryKeyBySource(selectedSource) || 'basic',
                wrapperRange: null,
                wrapperNode: selectedNode,
            });
            return;
        }
    }

    openForTextarea(document.getElementById(String(editorId || '')));
}

function syncTfMatrixStatementPreview(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    var preview = document.querySelector('[data-cbt-tfm-statement-preview="' + textarea.id + '"]');
    if (!(preview instanceof HTMLElement)) {
        return;
    }

    preview.innerHTML = sanitizeTfMatrixPreviewHtml(textarea.value || '');
    renderMathInContainer(preview);
}

function ensureQuicktagsEquationButton(editorId) {
    var toolbar = document.querySelector('#wp-' + editorId + '-wrap .quicktags-toolbar');
    if (!(toolbar instanceof HTMLElement) || toolbar.querySelector('[data-cbt-equation-trigger="editor"][data-cbt-equation-mode="text"][data-cbt-equation-target="' + editorId + '"]')) {
        return;
    }

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-small cbt-equation-toolbar-button';
    button.textContent = 'Equation';
    button.setAttribute('data-cbt-equation-trigger', 'editor');
    button.setAttribute('data-cbt-equation-target', editorId);
    button.setAttribute('data-cbt-equation-mode', 'text');
    toolbar.appendChild(button);
}

function ensureVisualEquationButton(editorId) {
    var wrap = document.getElementById('wp-' + editorId + '-wrap');
    if (!(wrap instanceof HTMLElement) || wrap.querySelector('[data-cbt-equation-trigger="editor"][data-cbt-equation-mode="visual"][data-cbt-equation-target="' + editorId + '"]')) {
        return;
    }

    var toolbarHost = wrap.querySelector('.wp-media-buttons') || wrap.querySelector('.mce-toolbar-grp');
    if (!(toolbarHost instanceof HTMLElement)) {
        return;
    }

    var host = document.createElement('div');
    host.className = 'cbt-equation-visual-toolbar';
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-small cbt-equation-toolbar-button cbt-equation-toolbar-button--visual';
    button.textContent = 'Equation';
    button.setAttribute('data-cbt-equation-trigger', 'editor');
    button.setAttribute('data-cbt-equation-target', editorId);
    button.setAttribute('data-cbt-equation-mode', 'visual');
    host.appendChild(button);
    toolbarHost.appendChild(host);
}

function enhanceManualQuestionEditors() {
    Array.from(document.querySelectorAll('textarea.wp-editor-area')).forEach(function (textarea) {
        var editorId = String(textarea.id || '');
        if (!matchesManualRichEditorId(editorId)) {
            return;
        }
        ensureQuicktagsEquationButton(editorId);
        ensureVisualEquationButton(editorId);
    });
}

function bindTfMatrixStatementFields() {
    Array.from(document.querySelectorAll('textarea[data-cbt-tfm-statement-field="1"]')).forEach(function (textarea) {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }
        if (textarea.dataset.cbtTfMatrixPreviewBound === '1') {
            syncTfMatrixStatementPreview(textarea);
            return;
        }
        textarea.addEventListener('input', function () {
            syncTfMatrixStatementPreview(textarea);
        });
        syncTfMatrixStatementPreview(textarea);
        textarea.dataset.cbtTfMatrixPreviewBound = '1';
    });
}

function bindModalEvents() {
    var refs = getEquationModalElements();
    if (refs.modal.dataset.cbtEquationModalBound === '1') {
        return;
    }

    refs.modal.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        if (target.closest('[data-cbt-equation-close="1"]')) {
            closeEquationModal();
            return;
        }

        var categoryButton = target.closest('[data-cbt-equation-category]');
        if (categoryButton instanceof HTMLElement) {
            authoringState.activeCategory = String(categoryButton.getAttribute('data-cbt-equation-category') || 'basic');
            renderTemplateList();
            return;
        }

        var templateButton = target.closest('[data-cbt-equation-template]');
        if (templateButton instanceof HTMLElement && refs.sourceField instanceof HTMLTextAreaElement) {
            refs.sourceField.value = getEquationTemplateSource(templateButton.getAttribute('data-cbt-equation-template'));
            refs.sourceField.focus();
            refs.sourceField.setSelectionRange(refs.sourceField.value.length, refs.sourceField.value.length);
            updateModalPreview();
            return;
        }

        var symbolButton = target.closest('[data-cbt-equation-symbol]');
        if (symbolButton instanceof HTMLElement) {
            var symbol = findQuickSymbolEntry(symbolButton.getAttribute('data-cbt-equation-symbol'));
            if (symbol) {
                insertTokenIntoSourceField(symbol.token);
            }
            return;
        }

        if (target.closest('[data-cbt-equation-use-suggestion="1"]')) {
            var suggestedDisplay = String(refs.useSuggestionButton instanceof HTMLButtonElement ? refs.useSuggestionButton.dataset.cbtEquationSuggestedDisplay || '' : '');
            var radio = refs.modal.querySelector('input[name="cbt-admin-equation-display"][value="' + normalizeEquationDisplayMode(suggestedDisplay) + '"]');
            if (radio instanceof HTMLInputElement) {
                radio.checked = true;
                updateModalPreview();
            }
        }
    });

    refs.sourceField.addEventListener('input', updateModalPreview);
    Array.from(refs.modal.querySelectorAll('input[name="cbt-admin-equation-display"]')).forEach(function (radio) {
        radio.addEventListener('change', updateModalPreview);
    });
    refs.applyButton.addEventListener('click', applyEquationToTarget);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !refs.modal.hidden) {
            closeEquationModal();
        }
    });

    refs.modal.dataset.cbtEquationModalBound = '1';
}

function bindAuthoringTriggerDelegation() {
    if (document.body.dataset.cbtEquationDelegationBound === '1') {
        return;
    }

    document.body.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        var trigger = target.closest('[data-cbt-equation-trigger]');
        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        var triggerType = String(trigger.getAttribute('data-cbt-equation-trigger') || '');
        if (triggerType === 'editor') {
            openForEditor(
                String(trigger.getAttribute('data-cbt-equation-target') || ''),
                String(trigger.getAttribute('data-cbt-equation-mode') || '')
            );
            return;
        }

        if (triggerType === 'tfm') {
            openForTextarea(document.getElementById(String(trigger.getAttribute('data-cbt-equation-target') || '')));
        }
    });

    document.body.dataset.cbtEquationDelegationBound = '1';
}

function ensureTfMatrixTriggerButtons() {
    Array.from(document.querySelectorAll('[data-cbt-tfm-equation-trigger]')).forEach(function (button) {
        if (!(button instanceof HTMLElement)) {
            return;
        }
        if (String(button.getAttribute('data-cbt-equation-trigger') || '') === 'editor') {
            return;
        }
        button.setAttribute('data-cbt-equation-trigger', 'tfm');
        button.setAttribute('data-cbt-equation-target', String(button.getAttribute('data-cbt-tfm-statement-target') || ''));
    });
}

function bindTinyMceLifecycle() {
    var tinyMceGlobal = getTinyMceGlobal();
    if (!tinyMceGlobal || tinyMceGlobal.__cbtEquationAddEditorBound || typeof tinyMceGlobal.on !== 'function') {
        return;
    }

    tinyMceGlobal.on('AddEditor', function (event) {
        var editor = event && event.editor ? event.editor : null;
        if (!editor || !matchesManualRichEditorId(editor.id)) {
            return;
        }

        window.setTimeout(function () {
            ensureVisualEquationButton(editor.id);
            ensureQuicktagsEquationButton(editor.id);
        }, 150);
    });

    tinyMceGlobal.__cbtEquationAddEditorBound = true;
}

export function bootAdminMathAuthoring() {
    if (!document.body || !document.getElementById('cbt-question-manual-form')) {
        return;
    }

    createEquationModal();
    bindModalEvents();
    bindAuthoringTriggerDelegation();
    ensureTfMatrixTriggerButtons();
    bindTfMatrixStatementFields();
    enhanceManualQuestionEditors();
    bindTinyMceLifecycle();

    window.setTimeout(function () {
        enhanceManualQuestionEditors();
    }, 300);

    window.CBTAdminMathAuthoring = {
        buildEquationHtml: buildEquationHtml,
        parseEquationWrapperMarkup: parseEquationWrapperMarkup,
        findEquationWrapperRange: findEquationWrapperRange,
        getEquationTemplateSource: getEquationTemplateSource,
        getEquationTemplateCatalog: getEquationTemplateCatalog,
        getQuickSymbolCatalog: getQuickSymbolCatalog,
        suggestEquationDisplayMode: suggestEquationDisplayMode,
        sanitizeTfMatrixPreviewHtml: sanitizeTfMatrixPreviewHtml,
        openForEditor: openForEditor,
        openForTfMatrixStatement: function (statementId) {
            openForTextarea(document.getElementById(String(statementId || '')));
        },
    };
}
