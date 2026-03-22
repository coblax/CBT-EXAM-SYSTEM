export function createBootstrapSessionManager(deps) {
    var clearMessages = deps.clearMessages;
    var fullLogout = deps.fullLogout;
    var loadExams = deps.loadExams;
    var persistAuthSession = deps.persistAuthSession;
    var readPersistedAuthSession = deps.readPersistedAuthSession;
    var render = deps.render;
    var startSessionHeartbeat = deps.startSessionHeartbeat;
    var state = deps.state;
    var triggerPendingSyncLifecycleRetry = deps.triggerPendingSyncLifecycleRetry;
    var tryResumeActiveAttemptFromExamList = deps.tryResumeActiveAttemptFromExamList;

    async function bootstrapFromPersistedSession() {
        var persisted = readPersistedAuthSession();
        if (!persisted) {
            render();
            return;
        }

        state.token = persisted.token;
        state.user = persisted.user;
        state.selectedExamId = persisted.selectedExamId;
        state.stage = 'confirm';
        state.busy = true;
        clearMessages();
        render();

        try {
            await loadExams();
            var resumed = await tryResumeActiveAttemptFromExamList({
                selectedOnly: Number(persisted.selectedExamId) > 0
            });
            if (resumed) {
                persistAuthSession();
                startSessionHeartbeat();
                state.busy = false;
                triggerPendingSyncLifecycleRetry('bootstrap-resume', {
                    delayMs: 220
                });
                render();
                return;
            }

            state.stage = 'confirm';
            state.error = '';
            state.success = '';
            persistAuthSession();
            startSessionHeartbeat();
            state.busy = false;
            triggerPendingSyncLifecycleRetry('bootstrap-session', {
                delayMs: 220
            });
            render();
        } catch (error) {
            if (!state.token) {
                state.busy = false;
                render();
                return;
            }

            fullLogout();
            state.error = error instanceof Error && error.message ? error.message : 'Sesi login berakhir. Silakan login lagi.';
            render();
        }
    }

    return {
        bootstrapFromPersistedSession: bootstrapFromPersistedSession
    };
}
