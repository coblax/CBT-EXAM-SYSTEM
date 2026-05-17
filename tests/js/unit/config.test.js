import { describe, it, expect, beforeEach } from 'vitest';
import {
    getFrontendConfig,
    createInitialState,
    FONT_SCALE_MIN,
    FONT_SCALE_MAX,
    FONT_SCALE_STEP,
    FONT_SCALE_DEFAULT,
    EXAM_TOKEN_LENGTH,
    NAV_SIDE_LAYOUT_BREAKPOINT,
    PANEL_STACK_BREAKPOINT,
    QUESTION_WINDOW_SIZE,
    NAV_QUESTION_FILTER_ALL,
    NAV_QUESTION_FILTER_ANSWERED,
    NAV_QUESTION_FILTER_UNANSWERED,
    NAV_QUESTION_FILTER_DOUBTFUL,
    AUTH_SESSION_STORAGE_KEY,
    UI_PREF_STORAGE_KEY
} from '../../../src/frontend/app/core/config';

describe('config', () => {
    describe('constants', () => {
        it('FONT_SCALE_MIN is 0.85', () => {
            expect(FONT_SCALE_MIN).toBe(0.85);
        });

        it('FONT_SCALE_MAX is 1.35', () => {
            expect(FONT_SCALE_MAX).toBe(1.35);
        });

        it('FONT_SCALE_STEP is 0.1', () => {
            expect(FONT_SCALE_STEP).toBe(0.1);
        });

        it('FONT_SCALE_DEFAULT is 1', () => {
            expect(FONT_SCALE_DEFAULT).toBe(1);
        });

        it('EXAM_TOKEN_LENGTH is 6', () => {
            expect(EXAM_TOKEN_LENGTH).toBe(6);
        });

        it('NAV_SIDE_LAYOUT_BREAKPOINT is defined', () => {
            expect(NAV_SIDE_LAYOUT_BREAKPOINT).toBe(1000);
        });

        it('PANEL_STACK_BREAKPOINT is defined', () => {
            expect(PANEL_STACK_BREAKPOINT).toBe(1100);
        });

        it('QUESTION_WINDOW_SIZE is 10', () => {
            expect(QUESTION_WINDOW_SIZE).toBe(10);
        });

        it('filter constants are strings', () => {
            expect(NAV_QUESTION_FILTER_ALL).toBe('all');
            expect(NAV_QUESTION_FILTER_ANSWERED).toBe('answered');
            expect(NAV_QUESTION_FILTER_UNANSWERED).toBe('unanswered');
            expect(NAV_QUESTION_FILTER_DOUBTFUL).toBe('doubtful');
        });

        it('storage keys are strings', () => {
            expect(typeof AUTH_SESSION_STORAGE_KEY).toBe('string');
            expect(typeof UI_PREF_STORAGE_KEY).toBe('string');
            expect(AUTH_SESSION_STORAGE_KEY).toContain('cbt_');
            expect(UI_PREF_STORAGE_KEY).toContain('cbt_');
        });
    });

    describe('getFrontendConfig', () => {
        it('returns defaults for empty window', () => {
            var config = getFrontendConfig({});
            expect(config.frontendMode).toBe('student');
            expect(config.securityForceFullscreen).toBe(false);
            expect(config.securityBlockCopyPaste).toBe(false);
            expect(config.tokenLength).toBe(6);
        });

        it('parses supervisor mode', () => {
            var config = getFrontendConfig({
                CBTExamFrontendConfig: { frontendMode: 'supervisor' }
            });
            expect(config.frontendMode).toBe('supervisor');
        });

        it('normalizes boolean flags', () => {
            var config = getFrontendConfig({
                CBTExamFrontendConfig: {
                    securityForceFullscreen: '1',
                    securityBlockCopyPaste: 'true',
                    securityLogEvents: 'yes'
                }
            });
            expect(config.securityForceFullscreen).toBe(true);
            expect(config.securityBlockCopyPaste).toBe(true);
            expect(config.securityLogEvents).toBe(true);
        });

        it('normalizes false boolean flags', () => {
            var config = getFrontendConfig({
                CBTExamFrontendConfig: {
                    securityForceFullscreen: '0',
                    securityBlockCopyPaste: 'false',
                    securityLogEvents: 'no'
                }
            });
            expect(config.securityForceFullscreen).toBe(false);
            expect(config.securityBlockCopyPaste).toBe(false);
            expect(config.securityLogEvents).toBe(false);
        });

        it('clamps watermark opacity', () => {
            var config = getFrontendConfig({
                CBTExamFrontendConfig: { securityExamWatermarkOpacity: 0.5 }
            });
            expect(config.securityExamWatermarkOpacity).toBeLessThanOrEqual(0.12);
        });

        it('handles null window', () => {
            var config = getFrontendConfig(null);
            expect(config.frontendMode).toBe('student');
        });

        it('preserves raw properties', () => {
            var config = getFrontendConfig({
                CBTExamFrontendConfig: {
                    examProgramName: 'UAS Semester 1',
                    studentFrontendUrl: 'https://example.com/ujian'
                }
            });
            expect(config.examProgramName).toBe('UAS Semester 1');
            expect(config.studentFrontendUrl).toBe('https://example.com/ujian');
        });
    });

    describe('createInitialState', () => {
        it('returns object with login stage', () => {
            var state = createInitialState({});
            expect(state.stage).toBe('login');
        });

        it('sets connectionStatus to online by default', () => {
            var state = createInitialState({});
            expect(state.connectionStatus).toBe('online');
        });

        it('sets connectionStatus to offline when navigator.onLine is false', () => {
            var state = createInitialState({
                navigator: { onLine: false }
            });
            expect(state.connectionStatus).toBe('offline');
        });

        it('initializes all key properties', () => {
            var state = createInitialState({});
            expect(state.busy).toBe(false);
            expect(state.error).toBe('');
            expect(state.token).toBe('');
            expect(state.exams).toEqual([]);
            expect(state.selectedExamId).toBe(0);
            expect(state.attemptId).toBe(0);
            expect(state.isFinishing).toBe(false);
            expect(state.fontScale).toBe(FONT_SCALE_DEFAULT);
            expect(state.uiTheme).toBe('light');
            expect(state.calculatorVisible).toBe(false);
            expect(state.navPanelVisible).toBe(true);
        });

        it('has doubtful and answers as empty objects', () => {
            var state = createInitialState({});
            expect(state.answers).toEqual({});
            expect(state.doubtful).toEqual({});
        });
    });
});
