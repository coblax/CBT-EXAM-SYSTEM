import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAnswerSyncManager } from '../../../src/frontend/app/exam/answer-sync.js';

function createFixture(overrides = {}) {
    var calls = {
        render: [],
        renderExamPartial: [],
        scheduleQuestionCachePersist: []
    };
    var state = Object.assign({
        attemptId: 55,
        connectionStatus: 'online',
        examLockedForPendingFinish: false,
        isFinishing: false,
        lastSyncError: '',
        pendingSyncCount: 0,
        selectedExamId: 9,
        stage: 'exam',
        syncBlockingReason: ''
    }, overrides.state || {});

    var manager = createAnswerSyncManager({
        answerSyncRetryBaseDelayMs: 200,
        answerSyncRetryMaxDelayMs: 1000,
        apiRequest: async function () {
            return {};
        },
        autoSaveBatchMaxItems: 5,
        autoSaveChoiceDelayCongestedMs: 800,
        autoSaveChoiceDelayMs: 300,
        autoSaveCongestedWindowMs: 1200,
        autoSaveTextDelayCongestedMs: 1000,
        autoSaveTextDelayMs: 500,
        diagnosticsManager: overrides.diagnosticsManager || null,
        getNavigatorConnectionStatus: overrides.getNavigatorConnectionStatus || function () {
            return 'online';
        },
        getQuestionById: function () {
            return null;
        },
        getQuestionDataGeneration: function () {
            return 1;
        },
        getQuestionPayloadById: function () {
            return null;
        },
        isQuestionRevisionRefreshActive: function () {
            return false;
        },
        maybeFinalizeLockedExam: function () {},
        normalizeExistingAnswerForQuestion: function (value) {
            return value;
        },
        normalizeStoredAutoSaveState: function (snapshot) {
            return snapshot || {};
        },
        payloadSignature: function (value) {
            return JSON.stringify(value || null);
        },
        questionAnswerPayload: function () {
            return {};
        },
        recordActionTrail: function () {},
        recordTimeline: function () {},
        render: function (reason, meta) {
            calls.render.push({
                meta: meta || null,
                reason: String(reason || '')
            });
        },
        renderExamPartial: function (regions, reason, meta) {
            calls.renderExamPartial.push({
                meta: meta || null,
                reason: String(reason || ''),
                regions: regions || {}
            });
            if (typeof overrides.renderExamPartial === 'function') {
                return overrides.renderExamPartial(regions, reason, meta);
            }
            return false;
        },
        scheduleQuestionCachePersist: function (delayMs) {
            calls.scheduleQuestionCachePersist.push(Number(delayMs) || 0);
        },
        state: state,
        windowRef: window
    });

    return {
        calls,
        manager,
        state
    };
}

beforeEach(function () {
    vi.useFakeTimers();
});

afterEach(function () {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('createAnswerSyncManager', function () {
    it('uses partial question footer patch when connection status changes in exam stage', function () {
        var fixture = createFixture({
            renderExamPartial: function () {
                return true;
            }
        });

        fixture.manager.setConnectionStatus('offline', {
            render: true,
            triggerRetry: false
        });

        expect(fixture.calls.renderExamPartial).toEqual([
            {
                meta: {
                    connectionStatus: 'offline',
                    pendingSyncCount: 0
                },
                reason: 'connection:offline',
                regions: {
                    notice: true,
                    questionFooterSync: true
                }
            }
        ]);
        expect(fixture.calls.render).toEqual([]);
    });

    it('uses partial notice and sync patch for recoverable sync failures', function () {
        var fixture = createFixture({
            renderExamPartial: function () {
                return true;
            }
        });
        var error = new Error('Koneksi terputus');
        error.code = 'network_error';
        error.status = 0;
        error.isNetworkError = true;

        fixture.manager.handleRecoverableAnswerSyncFailure(error, {
            persist: false,
            reason: 'flush-failed',
            render: true
        });

        expect(fixture.state.lastSyncError).toBe('Koneksi terputus');
        expect(fixture.calls.renderExamPartial).toEqual([
            {
                meta: {
                    lastSyncError: 'Koneksi terputus',
                    pendingSyncCount: 0
                },
                reason: 'flush-failed',
                regions: {
                    notice: true,
                    questionFooterSync: true
                }
            }
        ]);
        expect(fixture.calls.render).toEqual([]);
    });
});
