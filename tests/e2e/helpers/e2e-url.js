function configuredE2EBaseUrl(fallbackBaseUrl) {
    return String(
        process.env.CBT_E2E_WP_BASE_URL
        || fallbackBaseUrl
        || process.env.CBT_E2E_BASE_URL
        || 'http://localhost/wordpress'
    ).trim();
}

function configuredE2EFrontendUrl(fallbackBaseUrl) {
    return String(process.env.CBT_E2E_FRONTEND_URL || configuredE2EBaseUrl(fallbackBaseUrl)).trim();
}

function e2eUrl(pathname, fallbackBaseUrl) {
    const baseUrl = configuredE2EBaseUrl(fallbackBaseUrl);
    const normalizedBase = baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`;
    const normalizedPath = String(pathname || '').replace(/^\/+/, '');

    return new URL(normalizedPath, normalizedBase).toString();
}

function e2eFrontendUrl(fallbackBaseUrl) {
    const frontendUrl = configuredE2EFrontendUrl(fallbackBaseUrl);

    return new URL(frontendUrl).toString();
}

module.exports = {
    configuredE2EBaseUrl,
    configuredE2EFrontendUrl,
    e2eFrontendUrl,
    e2eUrl,
};
