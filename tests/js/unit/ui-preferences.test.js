import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createUiPreferencesManager } from '../../../src/frontend/app/core/ui-preferences';

describe('ui-preferences', () => {
    var state, root, storage, manager;

    beforeEach(() => {
        state = { fontScale: 1, uiTheme: 'light', navPanelPosition: 'top', calculatorPosition: 'bottom' };
        root = {
            style: { setProperty: vi.fn() },
            classList: { toggle: vi.fn() }
        };
        storage = {};
        manager = createUiPreferencesManager({
            root: root,
            state: state,
            getLocalStorage: () => ({
                getItem: (key) => storage[key] || null,
                setItem: (key, val) => { storage[key] = val; }
            }),
            storageKey: 'cbt_ui_prefs',
            windowRef: { innerWidth: 1200 }
        });
    });

    describe('normalizeFontScale', () => {
        it('returns default for NaN', () => {
            expect(manager.normalizeFontScale(NaN)).toBe(1);
        });

        it('clamps to min', () => {
            expect(manager.normalizeFontScale(0.1)).toBeGreaterThanOrEqual(0.5);
        });

        it('clamps to max', () => {
            expect(manager.normalizeFontScale(5)).toBeLessThanOrEqual(1.35);
        });

        it('rounds to 2 decimal places', () => {
            var result = manager.normalizeFontScale(1.234);
            expect(result).toBe(1.23);
        });
    });

    describe('normalizeTheme', () => {
        it('returns dark for dark', () => {
            expect(manager.normalizeTheme('dark')).toBe('dark');
            expect(manager.normalizeTheme('DARK')).toBe('dark');
        });

        it('returns light for anything else', () => {
            expect(manager.normalizeTheme('light')).toBe('light');
            expect(manager.normalizeTheme('')).toBe('light');
            expect(manager.normalizeTheme('custom')).toBe('light');
        });
    });

    describe('normalizeNavPanelPosition', () => {
        it('accepts valid positions', () => {
            expect(manager.normalizeNavPanelPosition('left')).toBe('left');
            expect(manager.normalizeNavPanelPosition('right')).toBe('right');
            expect(manager.normalizeNavPanelPosition('bottom')).toBe('bottom');
            expect(manager.normalizeNavPanelPosition('top')).toBe('top');
        });

        it('defaults to top for invalid', () => {
            expect(manager.normalizeNavPanelPosition('center')).toBe('top');
            expect(manager.normalizeNavPanelPosition('')).toBe('top');
        });
    });

    describe('normalizeCalculatorPanelPosition', () => {
        it('accepts valid positions', () => {
            expect(manager.normalizeCalculatorPanelPosition('top')).toBe('top');
            expect(manager.normalizeCalculatorPanelPosition('left')).toBe('left');
            expect(manager.normalizeCalculatorPanelPosition('right')).toBe('right');
        });

        it('defaults to bottom for invalid', () => {
            expect(manager.normalizeCalculatorPanelPosition('center')).toBe('bottom');
        });
    });

    describe('formatFontScaleLabel', () => {
        it('formats as percentage', () => {
            expect(manager.formatFontScaleLabel(1)).toBe('100%');
            expect(manager.formatFontScaleLabel(1.2)).toBe('120%');
        });
    });

    describe('applyUiPreferences', () => {
        it('sets CSS custom property for font scale', () => {
            state.fontScale = 1.2;
            manager.applyUiPreferences();
            expect(root.style.setProperty).toHaveBeenCalledWith('--cbt-font-scale', '1.2');
        });

        it('toggles theme classes', () => {
            state.uiTheme = 'dark';
            manager.applyUiPreferences();
            expect(root.classList.toggle).toHaveBeenCalledWith('cbt-theme-dark', true);
            expect(root.classList.toggle).toHaveBeenCalledWith('cbt-theme-light', false);
        });
    });

    describe('persistUiPreferences / readPersistedUiPreferences', () => {
        it('persists and reads back preferences', () => {
            state.uiTheme = 'dark';
            state.fontScale = 1.3;
            manager.persistUiPreferences();

            var read = manager.readPersistedUiPreferences();
            expect(read.theme).toBe('dark');
            expect(read.fontScale).toBe(1.3);
        });
    });

    describe('updateFontScale', () => {
        it('updates state and returns true on change', () => {
            var result = manager.updateFontScale(1.2);
            expect(result).toBe(true);
            expect(state.fontScale).toBe(1.2);
        });

        it('returns false when no change', () => {
            state.fontScale = 1;
            var result = manager.updateFontScale(1);
            expect(result).toBe(false);
        });
    });

    describe('toggleTheme', () => {
        it('toggles light to dark', () => {
            state.uiTheme = 'light';
            manager.toggleTheme();
            expect(state.uiTheme).toBe('dark');
        });

        it('toggles dark to light', () => {
            state.uiTheme = 'dark';
            manager.toggleTheme();
            expect(state.uiTheme).toBe('light');
        });
    });

    describe('updateNavPanelPosition', () => {
        it('updates and returns true on change', () => {
            expect(manager.updateNavPanelPosition('left')).toBe(true);
            expect(state.navPanelPosition).toBe('left');
        });

        it('returns false when same', () => {
            state.navPanelPosition = 'top';
            expect(manager.updateNavPanelPosition('top')).toBe(false);
        });
    });
});
