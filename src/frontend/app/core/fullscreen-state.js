export function createFullscreenStateManager(deps) {
    var documentRef = deps.documentRef;
    var render = deps.render;
    var state = deps.state;

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

    function syncFullscreenState(shouldRender) {
        var nextState = !!getFullscreenElement();
        if (state.isFullscreenActive === nextState) {
            return;
        }

        state.isFullscreenActive = nextState;

        if (shouldRender) {
            render();
        }
    }

    return {
        getFullscreenElement: getFullscreenElement,
        syncFullscreenState: syncFullscreenState
    };
}
