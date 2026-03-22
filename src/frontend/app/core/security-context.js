export function getSecurityViewportWidth(win, doc) {
    var documentWidth = doc && doc.documentElement
        ? Number(doc.documentElement.clientWidth) || 0
        : 0;
    var windowWidth = win ? Number(win.innerWidth) || 0 : 0;
    return Math.max(documentWidth, windowWidth, 0);
}

export function getSecurityViewportHeight(win, doc) {
    var documentHeight = doc && doc.documentElement
        ? Number(doc.documentElement.clientHeight) || 0
        : 0;
    var windowHeight = win ? Number(win.innerHeight) || 0 : 0;
    return Math.max(documentHeight, windowHeight, 0);
}

export function detectSecurityDevicePlatform(win) {
    var nav = win && win.navigator ? win.navigator : {};
    var ua = String(nav.userAgent || '').toLowerCase();
    var platform = String(nav.platform || '').toLowerCase();
    var uaDataPlatform = nav.userAgentData && typeof nav.userAgentData.platform === 'string'
        ? String(nav.userAgentData.platform).toLowerCase()
        : '';

    if (ua.indexOf('android') >= 0 || uaDataPlatform.indexOf('android') >= 0) {
        return 'android';
    }
    if (/\b(iphone|ipad|ipod)\b/.test(ua) || uaDataPlatform.indexOf('ios') >= 0) {
        return 'ios';
    }
    if (ua.indexOf('cros') >= 0 || uaDataPlatform.indexOf('chrome') >= 0) {
        return 'chromeos';
    }
    if (ua.indexOf('windows') >= 0 || platform.indexOf('win') >= 0 || uaDataPlatform.indexOf('win') >= 0) {
        return 'windows';
    }
    if (ua.indexOf('mac os') >= 0 || ua.indexOf('macintosh') >= 0 || platform.indexOf('mac') >= 0 || uaDataPlatform.indexOf('mac') >= 0) {
        return 'macos';
    }
    if (ua.indexOf('linux') >= 0 || platform.indexOf('linux') >= 0 || uaDataPlatform.indexOf('linux') >= 0 || platform.indexOf('x11') >= 0) {
        return 'linux';
    }

    return 'unknown';
}

export function detectSecurityDeviceType(win, doc) {
    var nav = win && win.navigator ? win.navigator : {};
    var ua = String(nav.userAgent || '').toLowerCase();
    var viewportWidth = getSecurityViewportWidth(win, doc);
    var touchPoints = Math.max(0, Number(nav.maxTouchPoints) || 0);
    var isCoarsePointer = false;
    var uaDataMobile = nav.userAgentData && typeof nav.userAgentData.mobile === 'boolean'
        ? nav.userAgentData.mobile
        : null;
    var isIpadLike = (ua.indexOf('ipad') >= 0)
        || (ua.indexOf('macintosh') >= 0 && touchPoints > 1);
    var isTabletUa = /\b(tablet|playbook|silk)\b/.test(ua)
        || /\bandroid(?!.*mobile)\b/.test(ua)
        || isIpadLike;
    var isMobileUa = /\bmobi|iphone|ipod|android.*mobile|windows phone\b/.test(ua);

    try {
        isCoarsePointer = !!(win && typeof win.matchMedia === 'function' && win.matchMedia('(pointer: coarse)').matches);
    } catch (error) {
        isCoarsePointer = false;
    }

    if (isTabletUa) {
        return 'tablet';
    }

    if (uaDataMobile === true || isMobileUa) {
        return 'mobile';
    }

    if (isCoarsePointer && touchPoints > 0 && viewportWidth > 0 && viewportWidth <= 820) {
        return 'mobile';
    }

    return 'desktop';
}

export function detectSecurityInputMode(win) {
    var nav = win && win.navigator ? win.navigator : {};
    var touchPoints = Math.max(0, Number(nav.maxTouchPoints) || 0);

    try {
        if (win && typeof win.matchMedia === 'function' && win.matchMedia('(pointer: coarse)').matches) {
            return 'touch';
        }
    } catch (error) {
        // Ignore matchMedia capability failures.
    }

    return touchPoints > 0 ? 'touch' : 'pointer';
}

export function buildSecurityClientContext(win, doc, baseContext) {
    var context = baseContext && typeof baseContext === 'object' ? Object.assign({}, baseContext) : {};
    var viewportWidth = getSecurityViewportWidth(win, doc);
    var viewportHeight = getSecurityViewportHeight(win, doc);

    if (!context.device_type) {
        context.device_type = detectSecurityDeviceType(win, doc);
    }
    if (!context.device_platform) {
        context.device_platform = detectSecurityDevicePlatform(win);
    }
    if (!context.input_mode) {
        context.input_mode = detectSecurityInputMode(win);
    }
    if (!context.viewport_width && viewportWidth > 0) {
        context.viewport_width = viewportWidth;
    }
    if (!context.viewport_height && viewportHeight > 0) {
        context.viewport_height = viewportHeight;
    }

    return context;
}
