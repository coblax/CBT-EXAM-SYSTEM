const path = require('path');
const { execFileSync } = require('child_process');

const helperScriptPath = path.resolve(__dirname, 'recovery-fixture.php');
const phpBinary = process.env.CBT_E2E_PHP_BINARY || 'php';

function parseJsonOrThrow(rawOutput, action) {
    try {
        return JSON.parse(String(rawOutput || '{}'));
    } catch (error) {
        throw new Error(`Gagal membaca JSON helper recovery untuk action "${action}": ${String(error.message || error)}`);
    }
}

function runRecoveryFixtureAction(action, payload) {
    const args = [helperScriptPath, String(action || 'fixture')];
    if (payload && Object.keys(payload).length > 0) {
        args.push(JSON.stringify(payload));
    }

    try {
        const stdout = execFileSync(phpBinary, args, {
            cwd: path.resolve(__dirname, '..', '..', '..'),
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        const parsed = parseJsonOrThrow(stdout, action);
        if (!parsed || parsed.ok !== true) {
            throw new Error(`Helper recovery mengembalikan payload non-ok untuk action "${action}".`);
        }
        return parsed;
    } catch (error) {
        const stderr = error && typeof error.stderr === 'string' ? error.stderr.trim() : '';
        const stdout = error && typeof error.stdout === 'string' ? error.stdout.trim() : '';
        const detail = stderr || stdout || String(error.message || error);
        throw new Error(`Recovery fixture helper gagal untuk action "${action}": ${detail}`);
    }
}

function getRecoveryFixture() {
    return runRecoveryFixtureAction('fixture').fixture;
}

function resetRecoveryFixture() {
    return runRecoveryFixtureAction('reset');
}

function getLatestRecoveryAttempt() {
    return runRecoveryFixtureAction('latest_attempt').attempt;
}

function invalidateRecoveryNonAttemptCache() {
    return runRecoveryFixtureAction('invalidate_non_attempt_cache');
}

function invalidateRecoveryAdminSideCache() {
    return runRecoveryFixtureAction('invalidate_admin_side_cache');
}

function saveRecoveryRemoteState(payload) {
    return runRecoveryFixtureAction('save_remote_state', payload);
}

module.exports = {
    getLatestRecoveryAttempt,
    getRecoveryFixture,
    invalidateRecoveryAdminSideCache,
    invalidateRecoveryNonAttemptCache,
    resetRecoveryFixture,
    saveRecoveryRemoteState,
};
