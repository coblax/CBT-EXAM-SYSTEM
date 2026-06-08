export function createBrowserStorageAccess(windowRef) {
    var cachedSessionStorage;
    var cachedLocalStorage;

    function getSessionStorage() {
        if (cachedSessionStorage !== undefined) {
            return cachedSessionStorage;
        }

        try {
            if (!windowRef || !windowRef.sessionStorage) {
                cachedSessionStorage = null;
                return cachedSessionStorage;
            }

            var probeKey = '__cbt_session_probe__';
            windowRef.sessionStorage.setItem(probeKey, '1');
            windowRef.sessionStorage.removeItem(probeKey);
            cachedSessionStorage = windowRef.sessionStorage;
        } catch (error) {
            cachedSessionStorage = null;
        }

        return cachedSessionStorage;
    }

    function getLocalStorage() {
        if (cachedLocalStorage !== undefined) {
            return cachedLocalStorage;
        }

        try {
            if (!windowRef || !windowRef.localStorage) {
                cachedLocalStorage = null;
                return cachedLocalStorage;
            }

            var probeKey = '__cbt_local_probe__';
            windowRef.localStorage.setItem(probeKey, '1');
            windowRef.localStorage.removeItem(probeKey);
            cachedLocalStorage = windowRef.localStorage;
        } catch (error) {
            cachedLocalStorage = null;
        }

        return cachedLocalStorage;
    }

    function getIndexedDb() {
        try {
            return windowRef && windowRef.indexedDB ? windowRef.indexedDB : null;
        } catch (error) {
            return null;
        }
    }

    function getCacheApi() {
        try {
            return windowRef && windowRef.caches && typeof windowRef.caches.open === 'function'
                ? windowRef.caches
                : null;
        } catch (error) {
            return null;
        }
    }

    function hasServiceWorkerController() {
        try {
            return !!(
                windowRef
                && windowRef.navigator
                && windowRef.navigator.serviceWorker
                && windowRef.navigator.serviceWorker.controller
            );
        } catch (error) {
            return false;
        }
    }

    function getStorageHealth() {
        var indexedDbAvailable = !!getIndexedDb();
        var localStorageAvailable = !!getLocalStorage();
        var sessionStorageAvailable = !!getSessionStorage();
        var cacheApiAvailable = !!getCacheApi();
        var serviceWorkerControlled = hasServiceWorkerController();
        var localAnswerStorageAvailable = indexedDbAvailable || localStorageAvailable || sessionStorageAvailable;
        var mode = 'memory_only';
        var warningLevel = 'unsafe';

        if (indexedDbAvailable) {
            mode = 'durable';
            warningLevel = 'ok';
        } else if (localStorageAvailable || sessionStorageAvailable) {
            mode = 'fallback';
            warningLevel = 'fallback';
        }

        return {
            cacheApiAvailable: cacheApiAvailable,
            indexedDbAvailable: indexedDbAvailable,
            localAnswerStorageAvailable: localAnswerStorageAvailable,
            localStorageAvailable: localStorageAvailable,
            mode: mode,
            serviceWorkerControlled: serviceWorkerControlled,
            sessionStorageAvailable: sessionStorageAvailable,
            warningLevel: warningLevel
        };
    }

    return {
        getCacheApi: getCacheApi,
        getIndexedDb: getIndexedDb,
        getLocalStorage: getLocalStorage,
        getStorageHealth: getStorageHealth,
        getSessionStorage: getSessionStorage
    };
}
