export function createExamRuntimeLoader(options) {
    options = options || {};

    var importRuntimeBundle = typeof options.importRuntimeBundle === 'function'
        ? options.importRuntimeBundle
        : function () {
            return import('../exam/runtime-bundle.js');
        };
    var instantiateBundle = typeof options.instantiateBundle === 'function'
        ? options.instantiateBundle
        : function (module) {
            return module;
        };
    var formatErrorMessage = typeof options.formatErrorMessage === 'function'
        ? options.formatErrorMessage
        : function (error, fallback) {
            var message = error instanceof Error && error.message ? error.message : '';
            return message === '' ? String(fallback || 'Runtime ujian gagal dimuat.') : message;
        };
    var onLoadStart = typeof options.onLoadStart === 'function' ? options.onLoadStart : function () {};
    var onLoadSuccess = typeof options.onLoadSuccess === 'function' ? options.onLoadSuccess : function () {};
    var onLoadError = typeof options.onLoadError === 'function' ? options.onLoadError : function () {};

    var runtimeBundle = null;
    var runtimeBundlePromise = null;
    var runtimeLoadError = '';
    var runtimePrefetched = false;
    var runtimePromiseIsPrefetch = false;

    function ensure(loadOptions) {
        loadOptions = loadOptions || {};

        if (runtimeBundle) {
            return Promise.resolve(runtimeBundle);
        }

        if (runtimeBundlePromise) {
            if (!loadOptions.prefetchOnly && runtimePromiseIsPrefetch) {
                return runtimeBundlePromise.catch(function () {
                    return ensure(loadOptions);
                });
            }
            return runtimeBundlePromise;
        }

        if (!loadOptions.prefetchOnly) {
            runtimeLoadError = '';
        }

        runtimePromiseIsPrefetch = !!loadOptions.prefetchOnly;
        onLoadStart(loadOptions);
        runtimeBundlePromise = Promise.resolve()
            .then(function () {
                return importRuntimeBundle(loadOptions);
            })
            .then(function (module) {
                runtimeBundle = instantiateBundle(module, loadOptions);
                runtimeLoadError = '';
                onLoadSuccess(runtimeBundle, loadOptions);
                return runtimeBundle;
            })
            .catch(function (error) {
                runtimeLoadError = formatErrorMessage(
                    error,
                    'Runtime ujian gagal dimuat. Periksa koneksi lalu coba lagi.'
                );
                if (loadOptions.prefetchOnly) {
                    runtimePrefetched = false;
                }
                onLoadError(error, runtimeLoadError, loadOptions);
                throw error;
            })
            .finally(function () {
                runtimeBundlePromise = null;
                runtimePromiseIsPrefetch = false;
            });

        return runtimeBundlePromise;
    }

    function prefetch() {
        if (runtimeBundle || runtimeBundlePromise || runtimePrefetched) {
            return;
        }

        runtimePrefetched = true;
        ensure({
            prefetchOnly: true
        }).catch(function () {});
    }

    function getBundle() {
        return runtimeBundle;
    }

    function getLoadError() {
        return runtimeLoadError;
    }

    return {
        ensure: ensure,
        getBundle: getBundle,
        getLoadError: getLoadError,
        prefetch: prefetch
    };
}
