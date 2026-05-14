export const LEGACY_HANDOFF_STORAGE_KEY = 'cbt_exam_frontend_legacy_handoff_v1';
export const LEGACY_HANDOFF_TTL_MS = 45000;

export function writeLegacyHandoffIntent(getSessionStorage, intent, nowMs) {
    var storage = typeof getSessionStorage === 'function' ? getSessionStorage() : null;
    var normalized = normalizeLegacyHandoffIntent(intent, nowMs);
    if (!storage || !normalized) {
        return false;
    }

    try {
        storage.setItem(LEGACY_HANDOFF_STORAGE_KEY, JSON.stringify(normalized));
        return true;
    } catch (error) {
        return false;
    }
}

export function consumeLegacyHandoffIntent(getSessionStorage, nowMs) {
    var storage = typeof getSessionStorage === 'function' ? getSessionStorage() : null;
    if (!storage) {
        return null;
    }

    var parsed = null;
    try {
        var raw = storage.getItem(LEGACY_HANDOFF_STORAGE_KEY);
        storage.removeItem(LEGACY_HANDOFF_STORAGE_KEY);
        if (!raw) {
            return null;
        }
        parsed = JSON.parse(raw);
    } catch (error) {
        return null;
    }

    var normalized = normalizeLegacyHandoffIntent(parsed, parsed && parsed.created_at);
    if (!normalized) {
        return null;
    }

    var currentMs = Number.isFinite(Number(nowMs)) ? Number(nowMs) : Date.now();
    if (currentMs - normalized.created_at > LEGACY_HANDOFF_TTL_MS) {
        return null;
    }

    return normalized;
}

export function clearLegacyHandoffIntent(getSessionStorage) {
    var storage = typeof getSessionStorage === 'function' ? getSessionStorage() : null;
    if (!storage) {
        return;
    }

    try {
        storage.removeItem(LEGACY_HANDOFF_STORAGE_KEY);
    } catch (error) {
        // Ignore storage failures.
    }
}

function normalizeLegacyHandoffIntent(intent, nowMs) {
    if (!intent || typeof intent !== 'object') {
        return null;
    }

    var action = String(intent.action || '').trim().toLowerCase();
    if (action !== 'start-exam') {
        return null;
    }

    var selectedExamId = Math.max(0, Number(intent.selected_exam_id || intent.selectedExamId) || 0);
    var createdAt = Number.isFinite(Number(nowMs)) ? Number(nowMs) : Date.now();

    return {
        action: action,
        selected_exam_id: selectedExamId,
        exam_token: String(intent.exam_token || intent.examToken || '').trim(),
        skip_exam_refresh: intent.skip_exam_refresh === true || intent.skipExamRefresh === true,
        created_at: createdAt
    };
}
