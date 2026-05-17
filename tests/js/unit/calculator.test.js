import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createCalculatorFeature } from '../../../src/frontend/app/features/calculator';

describe('calculator feature', () => {
    var state, root, manager;

    beforeEach(() => {
        state = {
            calculatorVisible: true,
            calculatorExpression: '',
            calculatorResult: '',
            calculatorError: '',
            calculatorPosition: 'bottom'
        };
        root = {
            querySelector: vi.fn(() => null)
        };
        manager = createCalculatorFeature({
            state: state,
            root: root,
            escapeHtml: (val) => String(val || '').replace(/</g, '&lt;').replace(/>/g, '&gt;'),
            isCompactViewport: () => false,
            getEffectiveCalculatorPanelPosition: () => state.calculatorPosition,
            normalizeCalculatorPanelPosition: (v) => {
                if (['top', 'left', 'right'].includes(v)) return v;
                return 'bottom';
            }
        });
    });

    describe('handleAction calc-key', () => {
        it('appends key to expression', () => {
            var result = manager.handleAction('calc-key', { getAttribute: () => '7' });
            expect(result.handled).toBe(true);
            expect(result.shouldRender).toBe(true);
            expect(state.calculatorExpression).toBe('7');
        });

        it('appends multiple keys', () => {
            manager.handleAction('calc-key', { getAttribute: () => '1' });
            manager.handleAction('calc-key', { getAttribute: () => '+' });
            manager.handleAction('calc-key', { getAttribute: () => '2' });
            expect(state.calculatorExpression).toBe('1+2');
        });

        it('ignores empty value', () => {
            var result = manager.handleAction('calc-key', { getAttribute: () => '' });
            expect(result.handled).toBe(true);
            expect(state.calculatorExpression).toBe('');
        });
    });

    describe('handleAction calc-clear', () => {
        it('clears expression and result', () => {
            state.calculatorExpression = '1+2';
            state.calculatorResult = '3';
            var result = manager.handleAction('calc-clear', {});
            expect(result.handled).toBe(true);
            expect(state.calculatorExpression).toBe('');
            expect(state.calculatorResult).toBe('');
        });
    });

    describe('handleAction calc-backspace', () => {
        it('removes last character', () => {
            state.calculatorExpression = '123';
            manager.handleAction('calc-backspace', {});
            expect(state.calculatorExpression).toBe('12');
        });

        it('handles empty expression', () => {
            state.calculatorExpression = '';
            manager.handleAction('calc-backspace', {});
            expect(state.calculatorExpression).toBe('');
        });
    });

    describe('handleAction calc-eval', () => {
        it('evaluates valid expression', () => {
            state.calculatorExpression = '2+3';
            var result = manager.handleAction('calc-eval', {});
            expect(result.handled).toBe(true);
            expect(state.calculatorResult).toBe('5');
            expect(state.calculatorError).toBe('');
        });

        it('sets error for invalid expression', () => {
            state.calculatorExpression = '2++';
            manager.handleAction('calc-eval', {});
            expect(state.calculatorError).not.toBe('');
        });
    });

    describe('handleAction set-calc-position', () => {
        it('changes calculator position', () => {
            var result = manager.handleAction('set-calc-position', { getAttribute: () => 'top' });
            expect(result.handled).toBe(true);
            expect(state.calculatorPosition).toBe('top');
        });
    });

    describe('handleAction unknown', () => {
        it('returns not handled', () => {
            var result = manager.handleAction('unknown-action', {});
            expect(result.handled).toBe(false);
        });
    });

    describe('handleEnterKey', () => {
        it('evaluates expression', () => {
            state.calculatorExpression = '10*5';
            var result = manager.handleEnterKey();
            expect(result.handled).toBe(true);
            expect(state.calculatorResult).toBe('50');
        });
    });

    describe('renderPanel', () => {
        it('returns HTML string', () => {
            var html = manager.renderPanel();
            expect(html).toContain('cbt-calc-panel');
            expect(html).toContain('KALKULATOR');
        });

        it('includes hidden class when not visible', () => {
            state.calculatorVisible = false;
            var html = manager.renderPanel();
            expect(html).toContain('is-hidden');
        });
    });

    describe('handleInput', () => {
        it('normalizes input value', () => {
            var target = { value: '2+3', getAttribute: () => null };
            Object.setPrototypeOf(target, HTMLInputElement.prototype);
            var result = manager.handleInput(target);
            expect(result).toBe(true);
            expect(state.calculatorExpression).toBe('2+3');
        });

        it('returns false for non-input element', () => {
            var result = manager.handleInput({});
            expect(result).toBe(false);
        });
    });
});
