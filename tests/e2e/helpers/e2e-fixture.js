const path = require('path');
const { execFileSync } = require('child_process');

const helperScriptPath = path.resolve(__dirname, 'e2e-fixture.php');
const phpBinary = process.env.CBT_E2E_PHP_BINARY || 'php';

function parseJsonOrThrow(rawOutput, action) {
    try {
        return JSON.parse(String(rawOutput || '{}'));
    } catch (error) {
        throw new Error(`Gagal membaca JSON helper E2E untuk action "${action}": ${String(error.message || error)}`);
    }
}

function runE2EFixtureAction(action, payload) {
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
            throw new Error(`Helper E2E mengembalikan payload non-ok untuk action "${action}".`);
        }
        return parsed;
    } catch (error) {
        const stderr = error && typeof error.stderr === 'string' ? error.stderr.trim() : '';
        const stdout = error && typeof error.stdout === 'string' ? error.stdout.trim() : '';
        const detail = stderr || stdout || String(error.message || error);
        throw new Error(`E2E fixture helper gagal untuk action "${action}": ${detail}`);
    }
}

function getE2ECatalog() {
    return runE2EFixtureAction('catalog').catalog;
}

function getE2EFixture(fixtureKey, userKey) {
    return runE2EFixtureAction('fixture', {
        fixture_key: fixtureKey,
        user_key: userKey,
    }).fixture;
}

function resetE2EFixture(fixtureKey, userKey) {
    return runE2EFixtureAction('reset', {
        fixture_key: fixtureKey,
        user_key: userKey,
    });
}

function getLatestE2EAttempt(fixtureKey, userKey) {
    return runE2EFixtureAction('latest_attempt', {
        fixture_key: fixtureKey,
        user_key: userKey,
    }).attempt;
}

function getE2EAttemptAnswers(fixtureKey, attemptId, userKey) {
    return runE2EFixtureAction('attempt_answers', {
        fixture_key: fixtureKey,
        user_key: userKey,
        attempt_id: attemptId,
    }).answers;
}

function invalidateE2ENonAttemptCache(fixtureKey, userKey) {
    return runE2EFixtureAction('invalidate_non_attempt_cache', {
        fixture_key: fixtureKey,
        user_key: userKey,
    });
}

function invalidateE2EAdminSideCache(fixtureKey, userKey) {
    return runE2EFixtureAction('invalidate_admin_side_cache', {
        fixture_key: fixtureKey,
        user_key: userKey,
    });
}

function saveE2ERemoteState(fixtureKey, payload, userKey) {
    return runE2EFixtureAction('save_remote_state', {
        fixture_key: fixtureKey,
        user_key: userKey,
        ...payload,
    });
}

function getE2EGlobalTokenMeta(fixtureKey = 'recovery_persistence', userKey = 'primary_student') {
    return runE2EFixtureAction('global_token', {
        fixture_key: fixtureKey,
        user_key: userKey,
    }).token_meta;
}

function setE2EGlobalToken(payload) {
    return runE2EFixtureAction('set_global_token', payload).token_meta;
}

function setE2ESecurityConfig(payload) {
    return runE2EFixtureAction('set_security', payload).security;
}

function updateE2EExamFixture(fixtureKey, payload, userKey) {
    return runE2EFixtureAction('update_exam', {
        fixture_key: fixtureKey,
        user_key: userKey,
        ...payload,
    });
}

function shiftLatestE2EAttemptStart(fixtureKey, payload, userKey) {
    return runE2EFixtureAction('shift_latest_attempt_start', {
        fixture_key: fixtureKey,
        user_key: userKey,
        ...payload,
    }).attempt;
}

function getRecentE2ESecurityLogs(payload = {}) {
    return runE2EFixtureAction('recent_security_logs', payload).logs;
}

function getE2EMustWatchAttempts(payload = {}) {
    return runE2EFixtureAction('must_watch_attempts', payload).attempts;
}

function clearE2ESecurityLogs(payload = {}) {
    return runE2EFixtureAction('clear_security_logs', payload);
}

function syncE2ESubjectBankQuestionsToFixture(fixtureKey, payload = {}, userKey) {
    return runE2EFixtureAction('sync_subject_bank_questions_to_fixture', {
        fixture_key: fixtureKey,
        user_key: userKey,
        ...payload,
    });
}

function getE2EExamQuestions(fixtureKey, userKey) {
    return runE2EFixtureAction('exam_questions', {
        fixture_key: fixtureKey,
        user_key: userKey,
    }).questions;
}

function ageE2ELoginSession(userKey, secondsAgo) {
    return runE2EFixtureAction('age_login_session', {
        user_key: userKey,
        seconds_ago: secondsAgo,
    });
}

module.exports = {
    ageE2ELoginSession,
    clearE2ESecurityLogs,
    getE2EAttemptAnswers,
    getE2ECatalog,
    getE2EExamQuestions,
    getE2EFixture,
    getE2EGlobalTokenMeta,
    getE2EMustWatchAttempts,
    getLatestE2EAttempt,
    getRecentE2ESecurityLogs,
    invalidateE2EAdminSideCache,
    invalidateE2ENonAttemptCache,
    resetE2EFixture,
    saveE2ERemoteState,
    setE2EGlobalToken,
    setE2ESecurityConfig,
    shiftLatestE2EAttemptStart,
    syncE2ESubjectBankQuestionsToFixture,
    updateE2EExamFixture,
};
