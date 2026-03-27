import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createSecurityLoggingManager } from '../../../src/frontend/app/core/security-logging.js';

function createSessionStorage() {
    var store = {};

    return {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
        },
        removeItem: function (key) {
            delete store[key];
        },
        setItem: function (key, value) {
            store[key] = String(value);
        }
    };
}

function createFixture(overrides = {}) {
    var fetchCalls = [];
    var sessionStorage = overrides.sessionStorage || createSessionStorage();
    var state = Object.assign({
        attemptId: 44,
        isFinishing: false,
        stage: 'exam',
        token: 'token-123'
    }, overrides.state || {});
    var windowRef = {
        clearTimeout: clearTimeout,
        location: {
            href: 'https://example.test/exam'
        },
        performance: {
            getEntriesByType: function () {
                return overrides.navigationType
                    ? [{ type: overrides.navigationType }]
                    : [];
            }
        },
        sessionStorage: sessionStorage,
        setTimeout: setTimeout
    };
    var documentRef = {
        hasFocus: function () {
            return true;
        },
        visibilityState: 'visible'
    };

    var manager = createSecurityLoggingManager({
        buildSecurityClientContext: function (context) {
            return Object.assign({}, context || {});
        },
        buildUrl: function (route) {
            return 'https://example.test/' + String(route || '');
        },
        documentRef: documentRef,
        fetchImpl: function (url, options) {
            fetchCalls.push({
                options: options,
                url: url
            });
            return Promise.resolve({
                ok: true
            });
        },
        isExamFullscreenRequired: function () {
            return true;
        },
        isSecurityLoggingActiveForAttempt: function () {
            return overrides.loggingActive !== false;
        },
        isSecurityLoggingEnabled: function () {
            return overrides.loggingEnabled !== false;
        },
        recordTimeline: function () {},
        state: state,
        windowBlurLogDelayMs: 800,
        windowRef: windowRef
    });

    return {
        fetchCalls: fetchCalls,
        manager: manager,
        sessionStorage: sessionStorage,
        state: state
    };
}

describe('createSecurityLoggingManager', function () {
    beforeEach(function () {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-03-27T01:15:00Z'));
    });

    it('stores a pending leave marker and emits a refresh event on reload resume', function () {
        var sharedSessionStorage = createSessionStorage();
        var leaveFixture = createFixture({
            navigationType: 'navigate',
            sessionStorage: sharedSessionStorage
        });

        leaveFixture.manager.logPageLeaveSecurityEvent('beforeunload');

        expect(leaveFixture.fetchCalls).toHaveLength(1);
        expect(JSON.parse(String(leaveFixture.fetchCalls[0].options.body)).event_type).toBe('page_leave');

        var reloadFixture = createFixture({
            navigationType: 'reload',
            sessionStorage: sharedSessionStorage
        });

        var reconciled = reloadFixture.manager.reconcilePendingPageRefreshSecurityEvent();

        expect(reconciled).toBe(true);
        expect(reloadFixture.fetchCalls).toHaveLength(1);
        expect(JSON.parse(String(reloadFixture.fetchCalls[0].options.body))).toMatchObject({
            attempt_id: 44,
            event_type: 'page_refresh',
            context: {
                source: 'reload_resume',
                unload_source: 'beforeunload',
                navigation_type: 'reload'
            }
        });
        expect(sharedSessionStorage.getItem('cbt_exam_frontend_pending_page_leave_v1')).toBe(null);
    });
});
