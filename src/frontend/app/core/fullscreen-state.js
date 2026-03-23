export function createFullscreenStateManager(deps) {
    var documentRef = deps.documentRef;
    var render = deps.render;
    var state = deps.state;
    var windowRef = deps.windowRef;
    var nativeFullscreenOverride = null;

    function normalizeFullscreenBoolean(value) {
        if (value === true || value === false) {
            return value;
        }

        if (typeof value === 'number') {
            return value !== 0;
        }

        if (typeof value === 'string') {
            var normalized = value.trim().toLowerCase();
            if (normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on' || normalized === 'active') {
                return true;
            }
            if (normalized === '0' || normalized === 'false' || normalized === 'no' || normalized === 'off' || normalized === 'inactive') {
                return false;
            }
        }

        return null;
    }

    function getNativeFullscreenBridge() {
        if (!windowRef || !windowRef.CBTNativeFullscreenBridge || typeof windowRef.CBTNativeFullscreenBridge !== 'object') {
            return null;
        }

        return windowRef.CBTNativeFullscreenBridge;
    }

    function getFullscreenElement() {
        if (documentRef.fullscreenElement) {
            return documentRef.fullscreenElement;
        }
        if (documentRef.webkitFullscreenElement) {
            return documentRef.webkitFullscreenElement;
        }
        if (documentRef.mozFullScreenElement) {
            return documentRef.mozFullScreenElement;
        }
        if (documentRef.msFullscreenElement) {
            return documentRef.msFullscreenElement;
        }
        return null;
    }

    function getNativeFullscreenState() {
        var bridge = getNativeFullscreenBridge();
        var nextState = null;

        if (bridge && typeof bridge.isActive === 'function') {
            try {
                nextState = normalizeFullscreenBoolean(bridge.isActive());
            } catch (error) {
                nextState = null;
            }

            if (nextState !== null) {
                return nextState;
            }
        }

        if (bridge && Object.prototype.hasOwnProperty.call(bridge, 'active')) {
            nextState = normalizeFullscreenBoolean(bridge.active);
            if (nextState !== null) {
                return nextState;
            }
        }

        if (windowRef && Object.prototype.hasOwnProperty.call(windowRef, '__CBT_NATIVE_FULLSCREEN_ACTIVE__')) {
            nextState = normalizeFullscreenBoolean(windowRef.__CBT_NATIVE_FULLSCREEN_ACTIVE__);
            if (nextState !== null) {
                return nextState;
            }
        }

        return nativeFullscreenOverride;
    }

    function setNativeFullscreenActive(active, shouldRender) {
        var normalized = normalizeFullscreenBoolean(active);
        if (normalized === null) {
            return false;
        }

        nativeFullscreenOverride = normalized;
        syncFullscreenState(shouldRender !== false);
        return true;
    }

    function requestNativeFullscreen() {
        var bridge = getNativeFullscreenBridge();
        var requestResult = null;

        if (!bridge) {
            return Promise.resolve(false);
        }

        try {
            if (typeof bridge.requestFullscreen === 'function') {
                requestResult = bridge.requestFullscreen();
            } else if (typeof bridge.enterFullscreen === 'function') {
                requestResult = bridge.enterFullscreen();
            } else {
                return Promise.resolve(false);
            }
        } catch (error) {
            return Promise.reject(error);
        }

        return Promise.resolve(requestResult).then(function (result) {
            var normalized = normalizeFullscreenBoolean(result);
            if (normalized !== null) {
                nativeFullscreenOverride = normalized;
            }
            syncFullscreenState(false);
            return state.isFullscreenActive || normalized === true;
        });
    }

    function exitNativeFullscreen() {
        var bridge = getNativeFullscreenBridge();
        var exitResult = null;

        if (!bridge) {
            nativeFullscreenOverride = false;
            syncFullscreenState(false);
            return Promise.resolve(false);
        }

        try {
            if (typeof bridge.exitFullscreen === 'function') {
                exitResult = bridge.exitFullscreen();
            } else if (typeof bridge.leaveFullscreen === 'function') {
                exitResult = bridge.leaveFullscreen();
            } else {
                nativeFullscreenOverride = false;
                syncFullscreenState(false);
                return Promise.resolve(false);
            }
        } catch (error) {
            nativeFullscreenOverride = false;
            syncFullscreenState(false);
            return Promise.reject(error);
        }

        return Promise.resolve(exitResult).then(function (result) {
            var normalized = normalizeFullscreenBoolean(result);
            nativeFullscreenOverride = normalized === null ? false : normalized;
            syncFullscreenState(false);
            return !state.isFullscreenActive;
        });
    }

    function syncFullscreenState(shouldRender) {
        var nextState = !!getFullscreenElement();
        if (!nextState && getNativeFullscreenState() === true) {
            nextState = true;
        }

        if (state.isFullscreenActive === nextState) {
            return;
        }

        state.isFullscreenActive = nextState;

        if (shouldRender) {
            render();
        }
    }

    return {
        exitNativeFullscreen: exitNativeFullscreen,
        getFullscreenElement: getFullscreenElement,
        getNativeFullscreenState: getNativeFullscreenState,
        requestNativeFullscreen: requestNativeFullscreen,
        setNativeFullscreenActive: setNativeFullscreenActive,
        syncFullscreenState: syncFullscreenState
    };
}
