var MEDIA_URL_PATTERN = /\.(?:avif|bmp|gif|jpe?g|m4a|m4v|mov|mp3|mp4|oga|ogg|png|svg|wav|webm|webp)(?:[?#].*)?$/i;
var MEDIA_PREWARM_MAX_URLS = 200;

function getBaseHref(windowRef) {
    return windowRef && windowRef.location && windowRef.location.href
        ? String(windowRef.location.href)
        : 'http://localhost/';
}

function getOrigin(windowRef) {
    try {
        return new URL(getBaseHref(windowRef)).origin;
    } catch (error) {
        return '';
    }
}

function normalizeMediaUrl(rawValue, windowRef) {
    var raw = String(rawValue || '').trim();
    var url;

    if (raw === '' || raw.indexOf('data:') === 0 || raw.indexOf('blob:') === 0) {
        return '';
    }

    try {
        url = new URL(raw, getBaseHref(windowRef));
    } catch (error) {
        return '';
    }

    if (url.origin !== getOrigin(windowRef)) {
        return '';
    }

    if (!MEDIA_URL_PATTERN.test(url.pathname + url.search)) {
        return '';
    }

    return url.href;
}

function addMediaUrl(target, rawValue, windowRef) {
    var normalized = normalizeMediaUrl(rawValue, windowRef);
    if (normalized && target.indexOf(normalized) < 0) {
        target.push(normalized);
    }
}

function extractSrcsetUrls(srcset) {
    return String(srcset || '')
        .split(',')
        .map(function (entry) {
            return String(entry || '').trim().split(/\s+/)[0] || '';
        })
        .filter(Boolean);
}

function extractHtmlMediaUrls(markup, windowRef) {
    var urls = [];
    var text = String(markup || '');
    var attrPattern = /\b(?:poster|src)\s*=\s*(?:"([^"]+)"|'([^']+)'|([^\s>]+))/gi;
    var srcsetPattern = /\bsrcset\s*=\s*(?:"([^"]+)"|'([^']+)'|([^\s>]+))/gi;
    var cssUrlPattern = /\burl\(\s*(?:"([^"]+)"|'([^']+)'|([^'")]+))\s*\)/gi;
    var match;

    while ((match = attrPattern.exec(text))) {
        addMediaUrl(urls, match[1] || match[2] || match[3] || '', windowRef);
    }

    while ((match = srcsetPattern.exec(text))) {
        extractSrcsetUrls(match[1] || match[2] || match[3] || '').forEach(function (url) {
            addMediaUrl(urls, url, windowRef);
        });
    }

    while ((match = cssUrlPattern.exec(text))) {
        addMediaUrl(urls, match[1] || match[2] || match[3] || '', windowRef);
    }

    return urls;
}

export function extractQuestionMediaUrlsFromPayload(payload, windowRef) {
    var urls = [];
    var visited = typeof WeakSet === 'function' ? new WeakSet() : null;

    function add(rawValue) {
        addMediaUrl(urls, rawValue, windowRef);
    }

    function visit(value, depth) {
        if (depth > 6 || value === null || value === undefined) {
            return;
        }

        if (typeof value === 'string') {
            add(value);
            if (value.indexOf('<') >= 0 || value.indexOf('src') >= 0 || value.indexOf('url(') >= 0) {
                extractHtmlMediaUrls(value, windowRef).forEach(add);
            }
            return;
        }

        if (Array.isArray(value)) {
            value.forEach(function (item) {
                visit(item, depth + 1);
            });
            return;
        }

        if (typeof value !== 'object') {
            return;
        }

        if (visited) {
            if (visited.has(value)) {
                return;
            }
            visited.add(value);
        }

        Object.keys(value).forEach(function (key) {
            visit(value[key], depth + 1);
        });
    }

    visit(payload, 0);
    return urls;
}

export function createQuestionMediaPrewarmManager(deps) {
    deps = deps || {};

    var windowRef = deps.windowRef || (typeof window !== 'undefined' ? window : globalThis);
    var diagnosticsManager = deps.diagnosticsManager || null;
    var recordTimeline = typeof deps.recordTimeline === 'function' ? deps.recordTimeline : null;
    var attemptKey = '';
    var prewarmedLookup = {};
    var prewarmedCount = 0;

    function resetForAttempt(nextAttemptKey) {
        attemptKey = nextAttemptKey;
        prewarmedLookup = {};
        prewarmedCount = 0;
    }

    function getServiceWorkerController() {
        try {
            return windowRef
                && windowRef.navigator
                && windowRef.navigator.serviceWorker
                && windowRef.navigator.serviceWorker.controller
                && typeof windowRef.navigator.serviceWorker.controller.postMessage === 'function'
                ? windowRef.navigator.serviceWorker.controller
                : null;
        } catch (error) {
            return null;
        }
    }

    function recordFailure(message, meta) {
        if (!diagnosticsManager || !diagnosticsManager.enabled || !recordTimeline) {
            return;
        }

        recordTimeline('media-prewarm:error', String(message || 'Media prewarm gagal.'), meta || {});
    }

    function prewarmQuestionMedia(payloads, meta) {
        meta = meta || {};

        var safeAttemptKey = String(Number(meta.attemptId) || 0);
        var controller = getServiceWorkerController();
        var nextUrls = [];

        if (safeAttemptKey !== attemptKey) {
            resetForAttempt(safeAttemptKey);
        }

        if (!controller || prewarmedCount >= MEDIA_PREWARM_MAX_URLS) {
            return [];
        }

        (Array.isArray(payloads) ? payloads : [payloads]).forEach(function (payload) {
            extractQuestionMediaUrlsFromPayload(payload, windowRef).forEach(function (url) {
                if (prewarmedLookup[url] || prewarmedCount + nextUrls.length >= MEDIA_PREWARM_MAX_URLS) {
                    return;
                }
                prewarmedLookup[url] = true;
                nextUrls.push(url);
            });
        });

        if (!nextUrls.length) {
            return [];
        }

        prewarmedCount += nextUrls.length;

        try {
            controller.postMessage({
                type: 'CBT_PRECACHE_MEDIA_URLS',
                urls: nextUrls
            });
        } catch (error) {
            recordFailure(error instanceof Error ? error.message : 'postMessage Service Worker gagal.', {
                attemptId: Number(meta.attemptId) || 0,
                count: nextUrls.length,
                reason: String(meta.reason || '')
            });
        }

        return nextUrls;
    }

    return {
        getPrewarmedUrlCount: function () {
            return prewarmedCount;
        },
        prewarmQuestionMedia: prewarmQuestionMedia
    };
}
