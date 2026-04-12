export function mountFrontendAppRuntime(deps) {
    var appEventManager = deps.appEventManager;
    var debugManager = deps.debugManager;
    var documentRef = deps.documentRef;
    var examSecurityManager = deps.examSecurityManager;
    var idleDetectionManager = deps.idleDetectionManager;
    var lifecycleManager = deps.lifecycleManager;
    var root = deps.root;

    function resolveEventElement(target) {
        if (target instanceof Element) {
            return target;
        }
        if (target && target.parentElement instanceof Element) {
            return target.parentElement;
        }
        return null;
    }

    function handleProfilePhotoRenderError(target) {
        if (!(target instanceof HTMLImageElement)) {
            return;
        }
        if (target.getAttribute('data-cbt-profile-photo') === null) {
            return;
        }
        if (target.getAttribute('data-cbt-profile-photo-error') === '1') {
            return;
        }

        target.setAttribute('data-cbt-profile-photo-error', '1');
        target.setAttribute('aria-hidden', 'true');
        target.hidden = true;

        var parent = target.parentElement;
        var fallback = parent instanceof Element
            ? parent.querySelector('[data-cbt-profile-photo-fallback]')
            : null;
        if (fallback instanceof HTMLElement) {
            fallback.hidden = false;
            fallback.removeAttribute('hidden');
        }
    }

    documentRef.addEventListener('pointerdown', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('capture:pointerdown', event, {
                withinRoot: String(Boolean(resolveEventElement(event.target) && root.contains(resolveEventElement(event.target))))
            });
        }
    }, true);

    documentRef.addEventListener('click', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('capture:click', event, {
                withinRoot: String(Boolean(resolveEventElement(event.target) && root.contains(resolveEventElement(event.target))))
            });
        }
    }, true);

    root.addEventListener('submit', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('root:submit', event);
        }
        appEventManager.handleSubmit(event.target, event);
    });

    root.addEventListener('click', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('root:click', event);
        }
        if (appEventManager.handleRootClick(event)) {
            event.__cbtRootActionHandled = true;
        }
    });

    root.addEventListener('pointerdown', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('root:pointerdown', event);
        }
        if (appEventManager.handlePointerDown(event)) {
            event.__cbtRootActionHandled = true;
        }
    });

    root.addEventListener('change', function (event) {
        appEventManager.handleChange(event.target);
    });

    root.addEventListener('error', function (event) {
        handleProfilePhotoRenderError(event.target);
    }, true);

    documentRef.addEventListener('click', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('document:click', event, {
                withinRoot: String(Boolean(resolveEventElement(event.target) && root.contains(resolveEventElement(event.target))))
            });
        }
        var target = resolveEventElement(event.target);
        if (
            !event.__cbtRootActionHandled
            && target instanceof Element
            && root.contains(target)
        ) {
            if (appEventManager.handleRootClick(event)) {
                event.__cbtRootActionHandled = true;
            }
        }
        appEventManager.handleDocumentClick(event);
    });

    documentRef.addEventListener('pointerdown', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('document:pointerdown', event, {
                withinRoot: String(Boolean(resolveEventElement(event.target) && root.contains(resolveEventElement(event.target))))
            });
        }
        var target = resolveEventElement(event.target);
        if (
            !event.__cbtRootActionHandled
            && target instanceof Element
            && root.contains(target)
        ) {
            if (appEventManager.handlePointerDown(event)) {
                event.__cbtRootActionHandled = true;
            }
        }
    });

    root.addEventListener('input', function (event) {
        appEventManager.handleInput(event.target);
    });

    documentRef.addEventListener('keydown', function (event) {
        if (debugManager && debugManager.enabled) {
            debugManager.log('document:keydown', {
                key: String(event && event.key ? event.key : '')
            });
        }
        appEventManager.handleKeydown(event);
    });

    examSecurityManager.mountSecurityListeners();
    if (idleDetectionManager && typeof idleDetectionManager.mountIdleListeners === 'function') {
        idleDetectionManager.mountIdleListeners();
    }
    lifecycleManager.mountLifecycleListeners();
}

export function startFrontendApp(deps) {
    var applyUiPreferences = deps.applyUiPreferences;
    var bootstrapFromPersistedSession = deps.bootstrapFromPersistedSession;
    var isCompactViewport = deps.isCompactViewport;
    var readPersistedUiPreferences = deps.readPersistedUiPreferences;
    var setCompactViewportState = deps.setCompactViewportState;
    var state = deps.state;
    var syncFullscreenState = deps.syncFullscreenState;

    var persistedUiPreferences = readPersistedUiPreferences();
    if (persistedUiPreferences) {
        state.uiTheme = persistedUiPreferences.theme;
        state.fontScale = persistedUiPreferences.fontScale;
        state.navPanelPosition = persistedUiPreferences.navPanelPosition;
        state.calculatorPosition = persistedUiPreferences.calculatorPosition;
    }

    setCompactViewportState(isCompactViewport());
    applyUiPreferences();
    syncFullscreenState(false);
    bootstrapFromPersistedSession();
}
