export function registerServiceWorker(config, deps) {
    deps = deps || {};
    var windowRef = deps.windowRef || (typeof window !== 'undefined' ? window : null);
    if (!windowRef) {
        return false;
    }

    var navigatorRef = deps.navigatorRef || windowRef.navigator || null;
    var documentRef = deps.documentRef || windowRef.document || null;
    var consoleRef = deps.consoleRef || windowRef.console || null;
    if (!config || !config.serviceWorkerEnabled || String(config.frontendMode || 'student') === 'supervisor') {
        return false;
    }
    if (!navigatorRef || !navigatorRef.serviceWorker || typeof navigatorRef.serviceWorker.register !== 'function') {
        return false;
    }

    var assetSource = String(config.frontendAssetSource || '').toLowerCase();
    if (assetSource.indexOf('vite dev server') >= 0 || assetSource.indexOf('constant override') >= 0) {
        return false;
    }

    var serviceWorkerUrl = String(config.serviceWorkerUrl || '').trim();
    var serviceWorkerScope = String(config.serviceWorkerScope || '').trim();
    if (serviceWorkerUrl === '' || serviceWorkerScope === '') {
        return false;
    }

    if (typeof navigatorRef.serviceWorker.addEventListener === 'function') {
        navigatorRef.serviceWorker.addEventListener('message', function (event) {
            var data = event && event.data && typeof event.data === 'object' ? event.data : null;
            if (!data || String(data.type || '') !== 'CBT_SW_ANSWER_SYNC_COMPLETE') {
                return;
            }
            if (typeof windowRef.dispatchEvent === 'function' && typeof windowRef.CustomEvent === 'function') {
                windowRef.dispatchEvent(new windowRef.CustomEvent('cbt:sw-answer-sync-complete', {
                    detail: {
                        remaining: Math.max(0, Number(data.remaining) || 0)
                    }
                }));
            }
        });
    }

    var register = function () {
        return navigatorRef.serviceWorker.register(serviceWorkerUrl, {
            scope: serviceWorkerScope
        }).catch(function (error) {
            if (consoleRef && typeof consoleRef.warn === 'function') {
                consoleRef.warn('CBT Service Worker registration failed:', error);
            }
            return null;
        });
    };

    if (documentRef && documentRef.readyState === 'complete') {
        register();
        return true;
    }

    if (typeof windowRef.addEventListener === 'function') {
        windowRef.addEventListener('load', register, { once: true });
        return true;
    }

    register();
    return true;
}
