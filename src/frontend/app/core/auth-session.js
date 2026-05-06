export function createAuthSessionManager(deps) {
    var state = deps.state;
    var getSessionStorage = deps.getSessionStorage;
    var storageKey = String(deps.storageKey || '');

    function normalizePersistedStage(rawStage) {
        var stage = String(rawStage || '').trim().toLowerCase();
        if (stage === 'exam' || stage === 'confirm' || stage === 'result' || stage === 'login') {
            return stage;
        }

        return '';
    }

    function normalizePersistedToken(rawToken) {
        if (typeof rawToken !== 'string') {
            return '';
        }

        var token = rawToken.trim();
        if (token === '' || token.length > 4096) {
            return '';
        }

        return token;
    }

    function normalizePersistedUser(rawUser) {
        if (!rawUser || typeof rawUser !== 'object') {
            return null;
        }

        var safeUser = {
            user_id: Number(rawUser.user_id) || 0,
            role: String(rawUser.role || ''),
            display_name: String(rawUser.display_name || ''),
            username: String(rawUser.username || ''),
            email: String(rawUser.email || ''),
            kode_kelas: String(rawUser.kode_kelas || ''),
            kode_ruang: String(rawUser.kode_ruang || ''),
            agama: String(rawUser.agama || ''),
            foto: String(rawUser.foto || '')
        };

        if (safeUser.user_id <= 0 || safeUser.role === '') {
            return null;
        }

        return safeUser;
    }

    function clearPersistedAuthSession() {
        var storage = getSessionStorage();
        if (!storage || storageKey === '') {
            return;
        }

        try {
            storage.removeItem(storageKey);
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }
    }

    function persistAuthSession(options) {
        options = options || {};
        var storage = getSessionStorage();
        if (!storage || storageKey === '') {
            return false;
        }

        if (!state.token || !state.user) {
            clearPersistedAuthSession();
            return false;
        }

        var normalizedToken = normalizePersistedToken(state.token);
        var payload = {
            token: normalizedToken,
            user: normalizePersistedUser(state.user),
            selected_exam_id: Number(state.selectedExamId) || 0,
            last_stage: normalizePersistedStage(state.stage)
        };

        if (!payload.user || payload.token === '') {
            clearPersistedAuthSession();
            return false;
        }

        try {
            if (options.skipIfStorageTokenDiffers === true) {
                var raw = storage.getItem(storageKey);
                if (raw) {
                    var parsed = JSON.parse(raw);
                    var storedToken = parsed && typeof parsed === 'object' ? normalizePersistedToken(parsed.token) : '';
                    if (storedToken !== '' && storedToken !== payload.token) {
                        return false;
                    }
                }
            }
            storage.setItem(storageKey, JSON.stringify(payload));
            return true;
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
            return false;
        }
    }

    function readPersistedAuthSession() {
        var storage = getSessionStorage();
        if (!storage || storageKey === '') {
            return null;
        }

        try {
            var raw = storage.getItem(storageKey);
            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return null;
            }

            var token = normalizePersistedToken(parsed.token);
            var user = normalizePersistedUser(parsed.user || null);
            var selectedExamId = Number(parsed.selected_exam_id) || 0;
            var lastStage = normalizePersistedStage(parsed.last_stage || '');

            if (token === '' || !user) {
                return null;
            }

            return {
                lastStage: lastStage,
                token: token,
                user: user,
                selectedExamId: selectedExamId
            };
        } catch (error) {
            return null;
        }
    }

    return {
        clearPersistedAuthSession: clearPersistedAuthSession,
        normalizePersistedStage: normalizePersistedStage,
        normalizePersistedToken: normalizePersistedToken,
        normalizePersistedUser: normalizePersistedUser,
        persistAuthSession: persistAuthSession,
        readPersistedAuthSession: readPersistedAuthSession
    };
}
