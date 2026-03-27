import { describe, expect, it } from 'vitest';
import { createNativeBridgeManager } from '../../../src/frontend/app/core/native-bridge.js';

function createWindowEmitter(overrides = {}) {
    var listeners = {};

    return Object.assign({
        addEventListener: function (eventName, callback) {
            if (!listeners[eventName]) {
                listeners[eventName] = [];
            }
            listeners[eventName].push(callback);
        },
        dispatchEvent: function (eventOrName, event) {
            var eventName = typeof eventOrName === 'string'
                ? eventOrName
                : String(eventOrName && eventOrName.type ? eventOrName.type : '');
            var payload = typeof eventOrName === 'string' ? (event || {}) : eventOrName;

            (listeners[eventName] || []).forEach(function (callback) {
                callback(payload);
            });

            return true;
        },
        removeEventListener: function (eventName, callback) {
            var bucket = listeners[eventName] || [];
            var index = bucket.indexOf(callback);

            if (index !== -1) {
                bucket.splice(index, 1);
            }
        }
    }, overrides || {});
}

function createManager(overrides = {}) {
    var state = Object.assign({
        token: 'jwt-token',
        attemptId: 114,
        stage: 'exam',
        selectedExamId: 16,
        user: {
            user_id: 7662
        }
    }, overrides.state || {});
    var windowRef = Object.assign({}, overrides.windowRef || {});

    var manager = createNativeBridgeManager({
        buildUrl: overrides.buildUrl || function (path) {
            return 'https://example.test/wp-json/cbt/v1/' + String(path || '');
        },
        isSecurityLoggingEnabled: overrides.isSecurityLoggingEnabled || function () {
            return true;
        },
        readPersistedAuthSession: overrides.readPersistedAuthSession || function () {
            return null;
        },
        state: state,
        windowRef: windowRef
    });

    return {
        manager: manager,
        state: state,
        windowRef: windowRef
    };
}

describe('createNativeBridgeManager', function () {
    it('returns a valid security snapshot while exam session is active', function () {
        var setup = createManager();

        setup.manager.mount();
        var snapshot = setup.windowRef.CBTNativeBridge.getSecuritySnapshot();

        expect(snapshot).toEqual({
            ok: 1,
            token: 'jwt-token',
            attemptId: 114,
            stage: 'exam',
            studentId: 7662,
            selectedExamId: 16,
            securityLoggingEnabled: true,
            endpoints: {
                nativeSecurityEvent: 'https://example.test/wp-json/cbt/v1/native_security_event'
            }
        });
    });

    it('keeps existing native bridge properties and returns safe empty auth snapshot outside exam stage', function () {
        var setup = createManager({
            state: {
                token: '',
                attemptId: 0,
                stage: 'login',
                selectedExamId: 9,
                user: null
            },
            readPersistedAuthSession: function () {
                return {
                    token: 'persisted-token',
                    selectedExamId: 9,
                    user: {
                        user_id: 55
                    }
                };
            },
            windowRef: {
                CBTNativeBridge: {
                    enterFullscreen: function () {
                        return true;
                    }
                }
            }
        });

        setup.manager.mount();
        var bridge = setup.windowRef.CBTNativeBridge;
        var snapshot = bridge.getSecuritySnapshot();

        expect(typeof bridge.enterFullscreen).toBe('function');
        expect(snapshot).toEqual({
            ok: 0,
            token: '',
            attemptId: 0,
            stage: 'login',
            studentId: 55,
            selectedExamId: 9,
            securityLoggingEnabled: true,
            endpoints: {}
        });
    });

    it('emits snapshot change notifications through callback and browser event only when the snapshot changes', function () {
        var setup = createManager({
            state: {
                token: '',
                attemptId: 0,
                stage: 'login',
                selectedExamId: 16,
                user: {
                    user_id: 7662
                }
            },
            windowRef: createWindowEmitter()
        });
        var callbackCalls = [];
        var eventCalls = [];

        setup.manager.mount();
        setup.windowRef.CBTNativeBridge.onSecuritySnapshotChanged = function (snapshot, reason) {
            callbackCalls.push({
                reason: reason,
                snapshot: snapshot
            });
        };
        setup.windowRef.addEventListener(setup.windowRef.CBTNativeBridge.getSecuritySnapshotChangedEventName(), function (event) {
            eventCalls.push(event.detail);
        });

        setup.manager.sync('mount');
        setup.manager.sync('mount-repeat');

        expect(callbackCalls).toHaveLength(1);
        expect(eventCalls).toHaveLength(1);
        expect(callbackCalls[0]).toMatchObject({
            reason: 'mount',
            snapshot: {
                ok: 0,
                attemptId: 0,
                stage: 'login'
            }
        });
        expect(eventCalls[0]).toMatchObject({
            reason: 'mount',
            snapshot: {
                ok: 0,
                attemptId: 0,
                stage: 'login'
            }
        });

        setup.state.token = 'jwt-token';
        setup.state.attemptId = 222;
        setup.state.stage = 'exam';
        setup.manager.sync('attempt-open');

        expect(callbackCalls).toHaveLength(2);
        expect(eventCalls).toHaveLength(2);
        expect(callbackCalls[1]).toMatchObject({
            reason: 'attempt-open',
            snapshot: {
                ok: 1,
                attemptId: 222,
                stage: 'exam',
                token: 'jwt-token'
            }
        });
        expect(eventCalls[1]).toMatchObject({
            reason: 'attempt-open',
            snapshot: {
                ok: 1,
                attemptId: 222,
                stage: 'exam',
                token: 'jwt-token'
            }
        });
    });

    it('allows JavaScript consumers to subscribe and unsubscribe from snapshot changes', function () {
        var setup = createManager({
            windowRef: createWindowEmitter()
        });
        var snapshots = [];

        setup.manager.mount();
        var unsubscribe = setup.windowRef.CBTNativeBridge.subscribeSecuritySnapshot(function (snapshot, reason) {
            snapshots.push({
                reason: reason,
                snapshot: snapshot
            });
        });

        setup.manager.sync('mount');
        unsubscribe();
        setup.state.stage = 'result';
        setup.state.attemptId = 0;
        setup.manager.sync('result');

        expect(snapshots).toHaveLength(1);
        expect(snapshots[0]).toMatchObject({
            reason: 'mount',
            snapshot: {
                ok: 1,
                attemptId: 114,
                stage: 'exam'
            }
        });
    });
});
