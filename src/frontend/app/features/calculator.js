import '../../styles/feature-calculator.css';
export function createCalculatorFeature(deps) {
    var state = deps.state;
    var root = deps.root;
    var escapeHtml = deps.escapeHtml;
    var isCompactViewport = deps.isCompactViewport;
    var getEffectiveCalculatorPanelPosition = deps.getEffectiveCalculatorPanelPosition;
    var normalizeCalculatorPanelPosition = deps.normalizeCalculatorPanelPosition;

    function normalizeCalculatorExpression(value) {
        return String(value || '')
            .replace(/[^0-9+\-*/().,%\s]/g, '')
            .replace(/\s+/g, '');
    }

    function formatCalculatorNumber(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            return '';
        }
        if (Math.abs(number - Math.round(number)) < 0.0000001) {
            return String(Math.round(number));
        }
        return number.toLocaleString('id-ID', {
            maximumFractionDigits: 8
        });
    }

    function evaluateCalculatorExpression(expression) {
        var normalizedExpression = normalizeCalculatorExpression(expression);
        if (normalizedExpression === '') {
            return {
                expression: '',
                result: '',
                error: ''
            };
        }

        if (/[%][^0-9(]/.test(normalizedExpression)) {
            return {
                expression: normalizedExpression,
                result: '',
                error: 'Penggunaan % tidak valid.'
            };
        }

        var openingCount = (normalizedExpression.match(/\(/g) || []).length;
        var closingCount = (normalizedExpression.match(/\)/g) || []).length;
        if (openingCount !== closingCount) {
            return {
                expression: normalizedExpression,
                result: '',
                error: 'Kurung tidak seimbang.'
            };
        }

        try {
            var result = Function('return (' + normalizedExpression + ');')();
            if (typeof result !== 'number' || !Number.isFinite(result)) {
                return {
                    expression: normalizedExpression,
                    result: '',
                    error: 'Hasil tidak valid.'
                };
            }

            return {
                expression: normalizedExpression,
                result: formatCalculatorNumber(result),
                error: ''
            };
        } catch (error) {
            return {
                expression: normalizedExpression,
                result: '',
                error: 'Ekspresi tidak valid.'
            };
        }
    }

    function applyCalculatorEvaluation() {
        var evaluation = evaluateCalculatorExpression(state.calculatorExpression);
        if (evaluation.error) {
            state.calculatorError = evaluation.error;
            state.calculatorResult = '';
            return false;
        }

        state.calculatorExpression = evaluation.expression;
        state.calculatorResult = evaluation.result;
        state.calculatorError = '';
        return true;
    }

    function focusInput() {
        var calculatorInput = root.querySelector('[name="calc_expression"]');
        if (!(calculatorInput instanceof HTMLInputElement)) {
            return;
        }

        calculatorInput.focus();
        var cursorPosition = calculatorInput.value.length;
        try {
            calculatorInput.setSelectionRange(cursorPosition, cursorPosition);
        } catch (error) {
            // Ignore browsers that block selection updates on input fields.
        }
    }

    function renderPositionControl(extraClass) {
        var options = [
            { value: 'top', label: 'Atas', arrow: '\u2191' },
            { value: 'left', label: 'Kiri', arrow: '\u2190' },
            { value: 'right', label: 'Kanan', arrow: '\u2192' },
            { value: 'bottom', label: 'Bawah', arrow: '\u2193' }
        ];
        var activePosition = getEffectiveCalculatorPanelPosition();
        var compactMode = isCompactViewport();
        var groupClass = 'cbt-access-group cbt-calc-position-group';

        if (extraClass) {
            groupClass += ' ' + String(extraClass);
        }

        return [
            '<div class="' + groupClass + '" role="group" aria-label="Posisi Kalkulator">',
            options.map(function (option) {
                var isActive = option.value === activePosition;
                var isDisabled = compactMode && (option.value === 'left' || option.value === 'right');
                var classes = 'cbt-icon-button cbt-calc-position-btn' + (isActive ? ' is-active' : '');
                return '<button class="' + classes + '" data-action="set-calc-position" data-position="' + escapeHtml(option.value) + '" type="button" aria-label="' + escapeHtml(option.label) + '" title="' + escapeHtml(option.label) + '"' + (isActive ? ' aria-pressed="true"' : ' aria-pressed="false"') + (isDisabled ? ' disabled aria-disabled="true"' : '') + '><span aria-hidden="true">' + escapeHtml(option.arrow) + '</span></button>';
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderPanel() {
        var panelClass = 'cbt-calc-panel' + (state.calculatorVisible ? '' : ' is-hidden');
        var statusClass = 'cbt-calc-status';
        var statusText = 'Ketik ekspresi lalu =';

        if (state.calculatorError) {
            statusClass += ' is-error';
            statusText = state.calculatorError;
        } else if (state.calculatorResult !== '') {
            statusClass += ' is-result';
            statusText = '= ' + state.calculatorResult;
        }

        return [
            '<aside class="' + panelClass + '" aria-hidden="' + (state.calculatorVisible ? 'false' : 'true') + '">',
            '<div class="cbt-calc-head">',
            '<div class="cbt-calc-head-title-wrap">',
            '<strong class="cbt-calc-head-title">KALKULATOR</strong>',
            '<p class="cbt-calc-head-subtitle">Hitung cepat di soal</p>',
            '</div>',
            '<div class="cbt-calc-head-actions">',
            renderPositionControl('cbt-calc-position-group-inline'),
            '<button class="cbt-icon-button cbt-calc-close" data-action="toggle-calculator" type="button" aria-label="Tutup kalkulator" title="Tutup kalkulator"><span class="cbt-calc-close-icon" aria-hidden="true">X</span><span class="cbt-visually-hidden">Tutup kalkulator</span></button>',
            '</div>',
            '</div>',
            '<div class="cbt-calc-display">',
            '<input class="cbt-input cbt-calc-expression" type="text" name="calc_expression" inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="(7+8)*2" value="' + escapeHtml(state.calculatorExpression) + '" />',
            '<div class="' + statusClass + '">' + escapeHtml(statusText) + '</div>',
            '</div>',
            '<div class="cbt-calc-grid" role="group" aria-label="Tombol kalkulator">',
            '<button class="cbt-calc-key cbt-calc-key-util" data-action="calc-clear" type="button">C</button>',
            '<button class="cbt-calc-key cbt-calc-key-util" data-action="calc-backspace" type="button">DEL</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="(" type="button">(</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value=")" type="button">)</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="7" type="button">7</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="8" type="button">8</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="9" type="button">9</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="/" type="button">/</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="4" type="button">4</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="5" type="button">5</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="6" type="button">6</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="*" type="button">*</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="1" type="button">1</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="2" type="button">2</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="3" type="button">3</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="-" type="button">-</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="0" type="button">0</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="." type="button">.</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="%" type="button">%</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="+" type="button">+</button>',
            '<button class="cbt-calc-key cbt-calc-key-eval" data-action="calc-eval" type="button">=</button>',
            '</div>',
            '</aside>'
        ].join('');
    }

    function handleAction(action, actionNode) {
        if (action === 'calc-key') {
            var calcKey = String(actionNode.getAttribute('data-value') || '');
            if (calcKey === '') {
                return { handled: true };
            }
            var nextExpression = String(state.calculatorExpression || '') + calcKey;
            state.calculatorExpression = normalizeCalculatorExpression(nextExpression);
            state.calculatorResult = '';
            state.calculatorError = '';
            return {
                handled: true,
                shouldRender: true,
                focusInput: true
            };
        }

        if (action === 'calc-clear') {
            state.calculatorExpression = '';
            state.calculatorResult = '';
            state.calculatorError = '';
            return {
                handled: true,
                shouldRender: true,
                focusInput: true
            };
        }

        if (action === 'calc-backspace') {
            var expression = String(state.calculatorExpression || '');
            state.calculatorExpression = expression.length > 0 ? expression.slice(0, -1) : '';
            state.calculatorResult = '';
            state.calculatorError = '';
            return {
                handled: true,
                shouldRender: true,
                focusInput: true
            };
        }

        if (action === 'calc-eval') {
            applyCalculatorEvaluation();
            return {
                handled: true,
                shouldRender: true,
                focusInput: true
            };
        }

        if (action === 'set-calc-position') {
            var requestedPosition = String(actionNode.getAttribute('data-position') || '');
            if (isCompactViewport() && (requestedPosition === 'left' || requestedPosition === 'right')) {
                return { handled: true };
            }
            state.calculatorPosition = normalizeCalculatorPanelPosition(requestedPosition);
            return {
                handled: true,
                shouldRender: true,
                focusInput: state.calculatorVisible
            };
        }

        return { handled: false };
    }

    function handleInput(target) {
        if (!(target instanceof HTMLInputElement)) {
            return false;
        }

        state.calculatorExpression = normalizeCalculatorExpression(target.value || '');
        if (target.value !== state.calculatorExpression) {
            target.value = state.calculatorExpression;
        }
        state.calculatorResult = '';
        state.calculatorError = '';
        return true;
    }

    function handleEnterKey() {
        applyCalculatorEvaluation();
        return {
            handled: true,
            shouldRender: true,
            focusInput: true
        };
    }

    return {
        focusInput: focusInput,
        handleAction: handleAction,
        handleEnterKey: handleEnterKey,
        handleInput: handleInput,
        renderPanel: renderPanel
    };
}
