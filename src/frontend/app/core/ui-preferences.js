import {
    FONT_SCALE_DEFAULT,
    FONT_SCALE_MAX,
    FONT_SCALE_MIN,
    NAV_SIDE_LAYOUT_BREAKPOINT,
    PANEL_STACK_BREAKPOINT
} from './config';
import { clamp } from './format';

export function createUiPreferencesManager(deps) {
    var root = deps.root;
    var state = deps.state;
    var getLocalStorage = deps.getLocalStorage;
    var storageKey = String(deps.storageKey || '');
    var win = deps.windowRef;

    function normalizeFontScale(value) {
        var numericValue = Number(value);
        if (!Number.isFinite(numericValue)) {
            return FONT_SCALE_DEFAULT;
        }

        var clampedValue = clamp(numericValue, FONT_SCALE_MIN, FONT_SCALE_MAX);
        return Math.round(clampedValue * 100) / 100;
    }

    function normalizeTheme(value) {
        return String(value || '').toLowerCase() === 'dark' ? 'dark' : 'light';
    }

    function normalizeNavPanelPosition(value) {
        var normalized = String(value || '').toLowerCase();
        if (normalized === 'left' || normalized === 'right' || normalized === 'bottom') {
            return normalized;
        }
        return 'top';
    }

    function normalizeCalculatorPanelPosition(value) {
        var normalized = String(value || '').toLowerCase();
        if (normalized === 'top' || normalized === 'left' || normalized === 'right') {
            return normalized;
        }
        return 'bottom';
    }

    function isCompactViewport() {
        return Boolean(win && win.innerWidth <= PANEL_STACK_BREAKPOINT);
    }

    function isCompactNavViewport() {
        return Boolean(win && win.innerWidth <= NAV_SIDE_LAYOUT_BREAKPOINT);
    }

    function getEffectiveNavPanelPosition() {
        var normalized = normalizeNavPanelPosition(state.navPanelPosition);
        if (isCompactNavViewport() && (normalized === 'left' || normalized === 'right')) {
            return 'top';
        }
        return normalized;
    }

    function getEffectiveCalculatorPanelPosition() {
        var normalized = normalizeCalculatorPanelPosition(state.calculatorPosition);
        if (isCompactViewport() && (normalized === 'left' || normalized === 'right')) {
            return 'bottom';
        }
        return normalized;
    }

    function formatFontScaleLabel(scale) {
        var normalized = normalizeFontScale(scale);
        return String(Math.round(normalized * 100)) + '%';
    }

    function applyUiPreferences() {
        if (!root) {
            return;
        }

        var fontScale = normalizeFontScale(state.fontScale);
        var theme = normalizeTheme(state.uiTheme);
        var navPanelPosition = normalizeNavPanelPosition(state.navPanelPosition);
        var calculatorPosition = normalizeCalculatorPanelPosition(state.calculatorPosition);

        state.fontScale = fontScale;
        state.uiTheme = theme;
        state.navPanelPosition = navPanelPosition;
        state.calculatorPosition = calculatorPosition;

        root.style.setProperty('--cbt-font-scale', String(fontScale));
        root.classList.toggle('cbt-theme-dark', theme === 'dark');
        root.classList.toggle('cbt-theme-light', theme !== 'dark');
    }

    function persistUiPreferences() {
        var storage = getLocalStorage();
        if (!storage || storageKey === '') {
            return;
        }

        var payload = {
            theme: normalizeTheme(state.uiTheme),
            font_scale: normalizeFontScale(state.fontScale),
            nav_position: normalizeNavPanelPosition(state.navPanelPosition),
            calc_position: normalizeCalculatorPanelPosition(state.calculatorPosition)
        };

        try {
            storage.setItem(storageKey, JSON.stringify(payload));
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }
    }

    function readPersistedUiPreferences() {
        var storage = getLocalStorage();
        if (!storage || storageKey === '') {
            return null;
        }

        try {
            var raw = storage.getItem(storageKey);
            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return null;
            }
            return {
                theme: normalizeTheme(parsed.theme),
                fontScale: normalizeFontScale(parsed.font_scale),
                navPanelPosition: normalizeNavPanelPosition(parsed.nav_position),
                calculatorPosition: normalizeCalculatorPanelPosition(parsed.calc_position)
            };
        } catch (error) {
            return null;
        }
    }

    function updateFontScale(nextScale) {
        var normalized = normalizeFontScale(nextScale);
        if (Math.abs(normalized - state.fontScale) < 0.001) {
            return false;
        }

        state.fontScale = normalized;
        persistUiPreferences();
        return true;
    }

    function toggleTheme() {
        state.uiTheme = state.uiTheme === 'dark' ? 'light' : 'dark';
        persistUiPreferences();
    }

    function updateNavPanelPosition(nextPosition) {
        var normalized = normalizeNavPanelPosition(nextPosition);
        if (normalized === state.navPanelPosition) {
            return false;
        }

        state.navPanelPosition = normalized;
        persistUiPreferences();
        return true;
    }

    function updateCalculatorPanelPosition(nextPosition) {
        var normalized = normalizeCalculatorPanelPosition(nextPosition);
        if (normalized === state.calculatorPosition) {
            return false;
        }

        state.calculatorPosition = normalized;
        persistUiPreferences();
        return true;
    }

    return {
        applyUiPreferences: applyUiPreferences,
        formatFontScaleLabel: formatFontScaleLabel,
        getEffectiveCalculatorPanelPosition: getEffectiveCalculatorPanelPosition,
        getEffectiveNavPanelPosition: getEffectiveNavPanelPosition,
        isCompactNavViewport: isCompactNavViewport,
        isCompactViewport: isCompactViewport,
        normalizeCalculatorPanelPosition: normalizeCalculatorPanelPosition,
        normalizeFontScale: normalizeFontScale,
        normalizeNavPanelPosition: normalizeNavPanelPosition,
        normalizeTheme: normalizeTheme,
        persistUiPreferences: persistUiPreferences,
        readPersistedUiPreferences: readPersistedUiPreferences,
        toggleTheme: toggleTheme,
        updateCalculatorPanelPosition: updateCalculatorPanelPosition,
        updateFontScale: updateFontScale,
        updateNavPanelPosition: updateNavPanelPosition
    };
}
