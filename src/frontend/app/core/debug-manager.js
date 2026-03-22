export function createNoopFrontendDebugManager() {
    return {
        enabled: false,
        mount: function () {},
        refresh: function () {},
        log: function () {},
        logEvent: function () {}
    };
}

export function createFrontendDebugManagerBridge() {
    var implementation = createNoopFrontendDebugManager();

    return {
        get enabled() {
            return Boolean(implementation && implementation.enabled);
        },
        setImplementation: function (nextImplementation) {
            if (nextImplementation && typeof nextImplementation === 'object') {
                implementation = nextImplementation;
                return;
            }

            implementation = createNoopFrontendDebugManager();
        },
        mount: function () {
            if (implementation && typeof implementation.mount === 'function') {
                implementation.mount();
            }
        },
        refresh: function () {
            if (implementation && typeof implementation.refresh === 'function') {
                implementation.refresh();
            }
        },
        log: function (kind, payload) {
            if (implementation && typeof implementation.log === 'function') {
                implementation.log(kind, payload);
            }
        },
        logEvent: function (kind, event, extra) {
            if (implementation && typeof implementation.logEvent === 'function') {
                implementation.logEvent(kind, event, extra);
            }
        }
    };
}
