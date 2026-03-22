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

    return {
        getIndexedDb: getIndexedDb,
        getLocalStorage: getLocalStorage,
        getSessionStorage: getSessionStorage
    };
}
