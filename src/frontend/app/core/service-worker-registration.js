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
