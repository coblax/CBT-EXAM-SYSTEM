import http from 'k6/http';
import exec from 'k6/execution';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { SharedArray } from 'k6/data';

function envString(name, fallback) {
    const raw = __ENV[name];
    if (raw === undefined || raw === null || String(raw).trim() === '') {
        return fallback;
    }
    return String(raw);
}

function envNumber(name, fallback) {
    const raw = __ENV[name];
    if (raw === undefined || raw === null || String(raw).trim() === '') {
        return fallback;
    }
    const parsed = Number(raw);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function envBool(name, fallback) {
    const raw = __ENV[name];
    if (raw === undefined || raw === null || String(raw).trim() === '') {
        return fallback;
    }
    return !['0', 'false', 'off', 'no', ''].includes(String(raw).trim().toLowerCase());
}

function defaultRequestTimeout(vus) {
    if (vus >= 1000) {
        return '60s';
    }
    if (vus >= 500) {
        return '45s';
    }
    return '30s';
}

function defaultSessionStartSpreadMs(vus) {
    if (vus >= 1000) {
        return 90000;
    }
    if (vus >= 500) {
        return 30000;
    }
    if (vus >= 200) {
        return 10000;
    }
    return 0;
}

function defaultPostStartSpreadMs(vus) {
    if (vus >= 1000) {
        return 30000;
    }
    if (vus >= 500) {
        return 10000;
    }
    if (vus >= 200) {
        return 3000;
    }
    return 0;
}

function defaultRetryCount(vus) {
    if (vus >= 1000) {
        return 2;
    }
    if (vus >= 500) {
        return 1;
    }
    return 0;
}

function normalizeLoadShape(rawLoadShape) {
    const normalized = String(rawLoadShape || '').trim().toLowerCase();
    if (normalized === 'ramping_vus') {
        return 'ramping_vus';
    }
    return 'flat_iterations';
}

function parseDurationToSeconds(rawDuration, fallbackSeconds) {
    const normalized = String(rawDuration || '').trim();
    const match = normalized.match(/^(\d+)([smh])$/);
    if (!match) {
        return fallbackSeconds;
    }
    const value = Number(match[1] || 0);
    const unit = String(match[2] || 's');
    if (unit === 'h') {
        return value * 3600;
    }
    if (unit === 'm') {
        return value * 60;
    }
    return value;
}

function secondsToDurationToken(seconds) {
    return String(Math.max(0, Number(seconds || 0))) + 's';
}

const BASE_URL = envString('BASE_URL', 'http://127.0.0.1').replace(/\/+$/, '');
const API_BASE = BASE_URL + '/wp-json/cbt/v1';
const EXAM_ID = envNumber('EXAM_ID', 0);
const EXAM_TOKEN = envString('EXAM_TOKEN', '').trim().toUpperCase();
const LOAD_SHAPE = normalizeLoadShape(envString('LOAD_SHAPE', 'flat_iterations'));
const VUS = envNumber('VUS', 1000);
const PEAK_VUS = envNumber('PEAK_VUS', VUS);
const ITERATIONS = envNumber('ITERATIONS', 1);
const WARMUP_DURATION = envString('WARMUP_DURATION', '1m');
const RAMP_UP_DURATION = envString('RAMP_UP_DURATION', '2m');
const STEADY_DURATION = envString('STEADY_DURATION', '5m');
const RAMP_DOWN_DURATION = envString('RAMP_DOWN_DURATION', '1m');
const RAMP_STEPS = Math.max(1, envNumber('RAMP_STEPS', 2));
const EFFECTIVE_VUS = LOAD_SHAPE === 'ramping_vus' ? Math.max(1, PEAK_VUS) : Math.max(1, VUS);
const MAX_DURATION = envString('MAX_DURATION', '45m');
const REQUEST_TIMEOUT = envString('REQUEST_TIMEOUT', defaultRequestTimeout(EFFECTIVE_VUS));
const QUESTIONS_PER_USER = envNumber('QUESTIONS_PER_USER', 0);
const THINK_MIN_MS = envNumber('THINK_MIN_MS', 100);
const THINK_MAX_MS = envNumber('THINK_MAX_MS', 250);
const SESSION_START_SPREAD_MS = envNumber('SESSION_START_SPREAD_MS', defaultSessionStartSpreadMs(EFFECTIVE_VUS));
const POST_START_SPREAD_MS = envNumber('POST_START_SPREAD_MS', defaultPostStartSpreadMs(EFFECTIVE_VUS));
const SUBMIT_PHASE_DELAY_MS = envNumber('SUBMIT_PHASE_DELAY_MS', 0);
const SUBMIT_PHASE_SPREAD_MS = envNumber('SUBMIT_PHASE_SPREAD_MS', 0);
const LEGACY_SUBMIT_MODE = envString('SUBMIT_MODE', 'all').trim().toLowerCase();
const LEGACY_ENABLE_BATCH_SUBMIT = envBool('ENABLE_BATCH_SUBMIT', true);
const BATCH_WINDOW_MS = envNumber('BATCH_WINDOW_MS', 2500);
const BATCH_MAX_ITEMS = envNumber('BATCH_MAX_ITEMS', 20);
const STRICT_EXAM_ID = envBool('STRICT_EXAM_ID', false);
const ENABLE_THRESHOLDS = envBool('ENABLE_THRESHOLDS', true);
const DEBUG_LOG = envBool('DEBUG_LOG', false);
const DEBUG_VU_LIMIT = envNumber('DEBUG_VU_LIMIT', 3);
const SKIP_EXAMS_REQUEST = envBool('SKIP_EXAMS_REQUEST', EXAM_ID > 0);
const LOGIN_RETRIES = Math.max(0, envNumber('LOGIN_RETRIES', 0));
const EXAMS_RETRIES = Math.max(0, envNumber('EXAMS_RETRIES', 0));
const START_ATTEMPT_RETRIES = Math.max(0, envNumber('START_ATTEMPT_RETRIES', defaultRetryCount(EFFECTIVE_VUS)));
const GET_QUESTIONS_RETRIES = Math.max(0, envNumber('GET_QUESTIONS_RETRIES', defaultRetryCount(EFFECTIVE_VUS)));
const RETRY_BACKOFF_MS = Math.max(250, envNumber('RETRY_BACKOFF_MS', 1500));
const USERS_FILE = './students.json';

function compileRampingStages(peakVus, warmupDuration, rampUpDuration, steadyDuration, rampDownDuration, rampSteps) {
    const stages = [];
    const safePeakVus = Math.max(1, Number(peakVus || 1));

    const warmupSeconds = parseDurationToSeconds(warmupDuration, 60);
    if (warmupSeconds > 0) {
        stages.push({
            target: Math.max(1, Math.ceil(safePeakVus * 0.15)),
            duration: secondsToDurationToken(warmupSeconds),
        });
    }

    const safeRampSteps = Math.max(1, Number(rampSteps || 1));
    const rampUpSeconds = parseDurationToSeconds(rampUpDuration, 120);
    if (rampUpSeconds > 0) {
        const baseSeconds = Math.floor(rampUpSeconds / safeRampSteps);
        const remainderSeconds = rampUpSeconds % safeRampSteps;
        for (let step = 1; step <= safeRampSteps; step += 1) {
            const durationSeconds = baseSeconds + (step <= remainderSeconds ? 1 : 0);
            if (durationSeconds <= 0) {
                continue;
            }
            stages.push({
                target: Math.max(1, Math.min(safePeakVus, Math.round((safePeakVus * step) / safeRampSteps))),
                duration: secondsToDurationToken(durationSeconds),
            });
        }
    }

    const steadySeconds = parseDurationToSeconds(steadyDuration, 300);
    if (steadySeconds > 0) {
        stages.push({
            target: safePeakVus,
            duration: secondsToDurationToken(steadySeconds),
        });
    }

    const rampDownSeconds = parseDurationToSeconds(rampDownDuration, 60);
    if (rampDownSeconds > 0) {
        stages.push({
            target: 0,
            duration: secondsToDurationToken(rampDownSeconds),
        });
    }

    return stages;
}

function normalizeScenarioKey(rawScenarioKey, legacySubmitMode, legacyBatchSubmit) {
    const normalized = String(rawScenarioKey || '').trim().toLowerCase();
    const allowed = [
        'login_only',
        'auth_exams_only',
        'start_attempt_only',
        'read_questions_only',
        'submit_sequential_only',
        'submit_batch_only',
        'full_exam_finish_sequential',
        'full_exam_finish_batch',
    ];
    if (allowed.includes(normalized)) {
        return normalized;
    }
    if (legacySubmitMode === 'none') {
        return 'read_questions_only';
    }
    return legacyBatchSubmit ? 'full_exam_finish_batch' : 'full_exam_finish_sequential';
}

function getScenarioConfig(scenarioKey) {
    const key = normalizeScenarioKey(scenarioKey, LEGACY_SUBMIT_MODE, LEGACY_ENABLE_BATCH_SUBMIT);
    const configs = {
        login_only: {
            key: 'login_only',
            readsQuestions: false,
            submitsAnswers: false,
            batchSubmit: false,
            finishExam: false,
            forceExamListRequest: false,
        },
        auth_exams_only: {
            key: 'auth_exams_only',
            readsQuestions: false,
            submitsAnswers: false,
            batchSubmit: false,
            finishExam: false,
            forceExamListRequest: true,
        },
        start_attempt_only: {
            key: 'start_attempt_only',
            readsQuestions: false,
            submitsAnswers: false,
            batchSubmit: false,
            finishExam: false,
            forceExamListRequest: false,
        },
        read_questions_only: {
            key: 'read_questions_only',
            readsQuestions: true,
            submitsAnswers: false,
            batchSubmit: false,
            finishExam: false,
            forceExamListRequest: false,
        },
        submit_sequential_only: {
            key: 'submit_sequential_only',
            readsQuestions: true,
            submitsAnswers: true,
            batchSubmit: false,
            finishExam: false,
            forceExamListRequest: false,
        },
        submit_batch_only: {
            key: 'submit_batch_only',
            readsQuestions: true,
            submitsAnswers: true,
            batchSubmit: true,
            finishExam: false,
            forceExamListRequest: false,
        },
        full_exam_finish_sequential: {
            key: 'full_exam_finish_sequential',
            readsQuestions: true,
            submitsAnswers: true,
            batchSubmit: false,
            finishExam: true,
            forceExamListRequest: false,
        },
        full_exam_finish_batch: {
            key: 'full_exam_finish_batch',
            readsQuestions: true,
            submitsAnswers: true,
            batchSubmit: true,
            finishExam: true,
            forceExamListRequest: false,
        },
    };

    return configs[key] || configs.full_exam_finish_batch;
}

const SCENARIO_KEY = normalizeScenarioKey(envString('SCENARIO_KEY', ''), LEGACY_SUBMIT_MODE, LEGACY_ENABLE_BATCH_SUBMIT);
const SCENARIO = getScenarioConfig(SCENARIO_KEY);
const SCENARIO_TAGS = { scenario: SCENARIO.key, load_shape: LOAD_SHAPE };
const RAMPING_STAGES = LOAD_SHAPE === 'ramping_vus'
    ? compileRampingStages(PEAK_VUS, WARMUP_DURATION, RAMP_UP_DURATION, STEADY_DURATION, RAMP_DOWN_DURATION, RAMP_STEPS)
    : [];

const users = new SharedArray('cbt-users', function () {
    const raw = open(USERS_FILE);
    const normalized = String(raw || '').replace(/^\uFEFF/, '');
    return JSON.parse(normalized);
});

if (!Array.isArray(users) || users.length === 0) {
    throw new Error(USERS_FILE + ' kosong atau tidak valid. Isi minimal 1 akun.');
}

const sessionSuccess = new Rate('exam_session_success');
const sessionFailure = new Counter('exam_session_failure');
const requestRetries = new Counter('cbt_request_retries');
const loginStageSuccess = new Rate('cbt_stage_login_success');
const examsStageSuccess = new Rate('cbt_stage_get_exams_success');
const startAttemptStageSuccess = new Rate('cbt_stage_start_attempt_success');
const getQuestionsStageSuccess = new Rate('cbt_stage_get_questions_success');
const submitSingleStageSuccess = new Rate('cbt_stage_submit_single_success');
const submitBatchStageSuccess = new Rate('cbt_stage_submit_batch_success');
const finishExamStageSuccess = new Rate('cbt_stage_finish_exam_success');
const loginDuration = new Trend('cbt_login_duration', true);
const startAttemptDuration = new Trend('cbt_start_attempt_duration', true);
const getQuestionsDuration = new Trend('cbt_get_questions_duration', true);
const submitAnswerDuration = new Trend('cbt_submit_answer_duration', true);
const submitAnswersBatchDuration = new Trend('cbt_submit_answers_batch_duration', true);
const finishExamDuration = new Trend('cbt_finish_exam_duration', true);

const defaultThresholds = {
    http_req_failed: ['rate<0.03'],
    http_req_duration: ['p(95)<1200', 'p(99)<2500'],
    exam_session_success: ['rate>0.95'],
};

if (SCENARIO.submitsAnswers && SCENARIO.batchSubmit) {
    defaultThresholds.cbt_submit_answers_batch_duration = ['p(95)<700'];
}

if (SCENARIO.submitsAnswers && !SCENARIO.batchSubmit) {
    defaultThresholds.cbt_submit_answer_duration = ['p(95)<900'];
}

export const options = {
    scenarios: {
        exam_sessions: LOAD_SHAPE === 'ramping_vus'
            ? {
                executor: 'ramping-vus',
                stages: RAMPING_STAGES,
                startVUs: 0,
                gracefulRampDown: '30s',
                gracefulStop: '30s',
            }
            : {
                executor: 'per-vu-iterations',
                vus: VUS,
                iterations: ITERATIONS,
                maxDuration: MAX_DURATION,
            },
    },
    thresholds: ENABLE_THRESHOLDS ? defaultThresholds : {},
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

if (EFFECTIVE_VUS >= 200 && (SCENARIO.startsAttempt || SCENARIO.readsQuestions || SCENARIO.submitsAnswers || SCENARIO.finishExam) && EXAM_ID <= 0) {
    console.warn('[k6] EXAM_ID belum diisi. Untuk load test tinggi, set EXAM_ID agar semua VU menembak exam yang sama dan bisa mengurangi request /exams.');
}

if (users.length < EFFECTIVE_VUS) {
    console.warn('[k6] Jumlah akun siswa (' + String(users.length) + ') lebih kecil dari target concurrency (' + String(EFFECTIVE_VUS) + '). Script akan reuse akun.');
}

function endpoint(path) {
    return API_BASE + '/' + String(path || '').replace(/^\/+/, '');
}

function parseJson(res) {
    try {
        return res.json();
    } catch (error) {
        return null;
    }
}

function extractItems(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }
    if (payload && Array.isArray(payload.items)) {
        return payload.items;
    }
    if (payload && payload.data && Array.isArray(payload.data)) {
        return payload.data;
    }
    if (payload && payload.data && Array.isArray(payload.data.items)) {
        return payload.data.items;
    }
    return [];
}

function logDebug(message) {
    if (!DEBUG_LOG) {
        return;
    }
    if (exec.vu.idInTest > Math.max(1, DEBUG_VU_LIMIT)) {
        return;
    }
    console.log('[k6][VU ' + String(exec.vu.idInTest) + '] ' + message);
}

function failSession(reason, details) {
    sessionFailure.add(1, { reason: reason, scenario: SCENARIO.key });
    sessionSuccess.add(0, SCENARIO_TAGS);
    if (details) {
        logDebug('FAIL ' + reason + ': ' + details);
        return;
    }
    logDebug('FAIL ' + reason);
}

function apiRequest(method, path, token, body) {
    const params = {
        timeout: REQUEST_TIMEOUT,
        headers: {
            Accept: 'application/json',
        },
    };
    if (token) {
        params.headers.Authorization = 'Bearer ' + token;
    }
    if (body !== undefined && body !== null) {
        params.headers['Content-Type'] = 'application/json';
    }
    return http.request(method, endpoint(path), body ? JSON.stringify(body) : null, params);
}

function isRetryableResponseStatus(status) {
    const code = Number(status) || 0;
    return code === 0 || code === 408 || code === 425 || code === 429 || code >= 500;
}

function randomBetween(minValue, maxValue) {
    const min = Math.min(minValue, maxValue);
    const max = Math.max(minValue, maxValue);
    return min + Math.floor(Math.random() * (max - min + 1));
}

function maybeThinkTime() {
    const waitMs = randomBetween(THINK_MIN_MS, THINK_MAX_MS);
    if (waitMs > 0) {
        sleep(waitMs / 1000);
    }
}

function staggerMsByVu(maxSpreadMs, vuNumber) {
    const spread = Math.max(0, Number(maxSpreadMs) || 0);
    if (spread <= 0) {
        return 0;
    }

    const totalVus = Math.max(1, VUS);
    if (totalVus <= 1) {
        return spread;
    }

    const slot = Math.max(0, (Number(vuNumber) || 1) - 1) % totalVus;
    return Math.floor((spread * slot) / (totalVus - 1));
}

function maybeSleepMs(waitMs) {
    const normalized = Math.max(0, Number(waitMs) || 0);
    if (normalized > 0) {
        sleep(normalized / 1000);
    }
}

function apiRequestWithRetry(method, path, token, body, retryCount, requestLabel) {
    let response = null;
    let totalDurationMs = 0;
    let attempts = 0;

    for (let currentAttempt = 0; currentAttempt <= retryCount; currentAttempt++) {
        response = apiRequest(method, path, token, body);
        attempts = currentAttempt + 1;
        totalDurationMs += Number(response && response.timings && response.timings.duration) || 0;

        if (!isRetryableResponseStatus(response && response.status) || currentAttempt >= retryCount) {
            return {
                response: response,
                totalDurationMs: totalDurationMs,
                attempts: attempts,
            };
        }

        const backoffMs = RETRY_BACKOFF_MS * (currentAttempt + 1) + randomBetween(100, 400);
        requestRetries.add(1, { endpoint: requestLabel, scenario: SCENARIO.key });
        logDebug(
            'retry ' +
            requestLabel +
            ' attempt=' +
            String(currentAttempt + 2) +
            ' status=' +
            String(response.status) +
            ' wait=' +
            String(backoffMs) +
            'ms'
        );
        maybeSleepMs(backoffMs);
        totalDurationMs += backoffMs;
    }

    return {
        response: response,
        totalDurationMs: totalDurationMs,
        attempts: attempts,
    };
}

function resolveExam(examItems) {
    if (!Array.isArray(examItems) || examItems.length === 0) {
        return null;
    }

    if (EXAM_ID > 0) {
        for (let i = 0; i < examItems.length; i++) {
            if (Number(examItems[i] && examItems[i].id) === EXAM_ID) {
                return examItems[i];
            }
        }
        if (STRICT_EXAM_ID) {
            return null;
        }
    }

    for (let i = 0; i < examItems.length; i++) {
        if (Number(examItems[i] && examItems[i].is_available_now) === 1) {
            return examItems[i];
        }
    }

    return examItems[0];
}

function stableHash(input) {
    const text = String(input || '');
    let hash = 2166136261;
    for (let i = 0; i < text.length; i++) {
        hash ^= text.charCodeAt(i);
        hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
}

function seededIndex(max, seed) {
    const limit = Math.max(0, Number(max) || 0);
    if (limit <= 0) {
        return 0;
    }
    return stableHash(seed) % limit;
}

function buildAnswerPayload(question, index, vuNumber) {
    const type = String((question && question.question_type) || '');
    const options = Array.isArray(question && question.options) ? question.options : [];
    const questionId = Number(question && question.id) || 0;
    const answerSeed = String(vuNumber) + ':' + String(questionId) + ':' + String(index);

    if (type === 'multiple_choice' || type === 'true_false') {
        if (!options.length) {
            return null;
        }

        const optionIndex = seededIndex(options.length, answerSeed + ':single');
        return Number(options[optionIndex] && options[optionIndex].id) || null;
    }

    if (type === 'multiple_answer') {
        if (!options.length) {
            return null;
        }

        const firstIndex = seededIndex(options.length, answerSeed + ':multi:first');
        const first = Number(options[firstIndex] && options[firstIndex].id) || 0;
        if (first <= 0) {
            return null;
        }

        const picked = [first];
        if (options.length >= 2 && seededIndex(4, answerSeed + ':multi:count') === 0) {
            let secondIndex = seededIndex(options.length, answerSeed + ':multi:second');
            if (secondIndex === firstIndex) {
                secondIndex = (secondIndex + 1) % options.length;
            }

            const second = Number(options[secondIndex] && options[secondIndex].id) || 0;
            if (second > 0 && second !== first) {
                picked.push(second);
            }
        }

        return picked;
    }

    if (type === 'true_false_matrix') {
        const meta = question && question.true_false_matrix_meta ? question.true_false_matrix_meta : null;
        const items = meta && Array.isArray(meta.items) ? meta.items : [];
        const itemCount = Math.max(
            0,
            items.length,
            Number(meta && meta.item_count) || 0
        );
        if (itemCount <= 0) {
            return null;
        }

        const payload = {};
        for (let matrixIndex = 0; matrixIndex < itemCount; matrixIndex++) {
            const item = items[matrixIndex] || null;
            const keyCandidate = item && item.key !== undefined ? String(item.key).trim() : '';
            const key = keyCandidate !== '' ? keyCandidate : String(matrixIndex + 1);

            payload[key] = seededIndex(2, answerSeed + ':matrix:' + key) === 0 ? 'true' : 'false';
        }
        return payload;
    }

    if (type === 'short_answer') {
        const meta = question && question.short_answer_meta ? question.short_answer_meta : null;
        const keys = meta && Array.isArray(meta.input_keys) ? meta.input_keys : ['A'];
        const firstKeyRaw = String(keys[0] || 'A').trim().toLowerCase();
        const firstKey = firstKeyRaw.replace(/^input[_-]?/, '');
        const payload = {};
        payload['input_' + firstKey] = 'jawaban-' + String(seededIndex(100000, answerSeed + ':short'));
        return payload;
    }

    if (type === 'essay') {
        return 'Ini jawaban essay simulasi VU ' + String(vuNumber) + ' soal ' + String(questionId || (index + 1)) + '.';
    }

    return 'jawaban-' + String(seededIndex(100000, answerSeed + ':text'));
}

function submitAnswerBatch(token, attemptId, batchItems) {
    if (!Array.isArray(batchItems) || batchItems.length === 0) {
        return { ok: true, status: 200, payload: { accepted_count: 0 } };
    }

    const batchRes = apiRequest('POST', 'submit_answers_batch', token, {
        attempt_id: attemptId,
        answers: batchItems,
    });
    submitAnswersBatchDuration.add(batchRes.timings.duration, SCENARIO_TAGS);
    const batchPayload = parseJson(batchRes);
    const batchOk = check(batchRes, {
        'submit answers batch status 200': (r) => r.status === 200,
    });

    return {
        ok: batchOk,
        status: batchRes.status,
        payload: batchPayload,
    };
}

export default function () {
    const vuNumber = exec.vu.idInTest;
    const user = users[(vuNumber - 1) % users.length];
    const identifier = String(user && user.identifier ? user.identifier : '').trim();
    const password = String(user && user.password ? user.password : '');

    maybeSleepMs(staggerMsByVu(SESSION_START_SPREAD_MS, vuNumber));

    if (identifier === '' || password === '') {
        failSession('invalid_user_credentials', 'identifier/password kosong');
        return;
    }

    const loginRequest = apiRequestWithRetry('POST', 'login', '', {
        identifier: identifier,
        password: password,
    }, LOGIN_RETRIES, 'login');
    const loginRes = loginRequest.response;
    loginDuration.add(loginRequest.totalDurationMs, SCENARIO_TAGS);
    const loginBody = parseJson(loginRes);
    const loginOk = check(loginRes, {
        'login status 200': (r) => r.status === 200,
        'login token exists': () => !!(loginBody && loginBody.token),
    });
    loginStageSuccess.add(loginOk ? 1 : 0, SCENARIO_TAGS);
    if (!loginOk) {
        failSession('login_failed', 'status=' + String(loginRes.status));
        return;
    }

    const token = String(loginBody.token || '');
    if (SCENARIO.key === 'login_only') {
        sessionSuccess.add(1, SCENARIO_TAGS);
        return;
    }

    let selectedExam = null;
    const shouldRequestExamList = SCENARIO.forceExamListRequest || !(EXAM_ID > 0 && SKIP_EXAMS_REQUEST);
    if (shouldRequestExamList) {
        const examsRequest = apiRequestWithRetry('GET', 'exams', token, null, EXAMS_RETRIES, 'exams');
        const examsRes = examsRequest.response;
        const examsBody = parseJson(examsRes);
        const examsOk = check(examsRes, {
            'get exams status 200': (r) => r.status === 200,
        });
        examsStageSuccess.add(examsOk ? 1 : 0, SCENARIO_TAGS);
        if (!examsOk) {
            failSession('get_exams_failed', 'status=' + String(examsRes.status));
            return;
        }

        const examItems = extractItems(examsBody);
        if (!examItems.length) {
            failSession('empty_exam_list');
            return;
        }

        if (SCENARIO.key === 'auth_exams_only') {
            sessionSuccess.add(1, SCENARIO_TAGS);
            return;
        }

        selectedExam = resolveExam(examItems);
        if (!selectedExam) {
            failSession('exam_not_found', 'EXAM_ID=' + String(EXAM_ID));
            return;
        }

        if (EXAM_ID > 0 && Number(selectedExam.id) !== EXAM_ID) {
            logDebug('EXAM_ID ' + String(EXAM_ID) + ' tidak ditemukan; fallback ke exam_id=' + String(selectedExam.id));
        }
    } else {
        selectedExam = { id: EXAM_ID };
    }

    const startBody = {
        exam_id: Number(selectedExam.id) || 0,
    };
    if (EXAM_TOKEN !== '') {
        startBody.exam_token = EXAM_TOKEN;
    }

    const startRequest = apiRequestWithRetry('POST', 'start_attempt', token, startBody, START_ATTEMPT_RETRIES, 'start_attempt');
    const startRes = startRequest.response;
    startAttemptDuration.add(startRequest.totalDurationMs, SCENARIO_TAGS);
    const startPayload = parseJson(startRes);
    const startOk = check(startRes, {
        'start attempt status 200': (r) => r.status === 200,
        'start attempt id exists': () => !!(startPayload && Number(startPayload.attempt_id) > 0),
    });
    startAttemptStageSuccess.add(startOk ? 1 : 0, SCENARIO_TAGS);
    if (!startOk) {
        const startCode = startPayload && (startPayload.code || (startPayload.data && startPayload.data.code));
        failSession('start_attempt_failed', 'status=' + String(startRes.status) + (startCode ? ',code=' + String(startCode) : ''));
        return;
    }

    const attemptId = Number(startPayload.attempt_id) || 0;
    const examId = Number(selectedExam.id) || 0;
    if (SCENARIO.key === 'start_attempt_only') {
        sessionSuccess.add(1, SCENARIO_TAGS);
        return;
    }
    maybeSleepMs(staggerMsByVu(POST_START_SPREAD_MS, vuNumber));

    const questionsRequest = apiRequestWithRetry(
        'GET',
        'questions?exam_id=' + examId + '&attempt_id=' + attemptId,
        token,
        null,
        GET_QUESTIONS_RETRIES,
        'questions'
    );
    const questionsRes = questionsRequest.response;
    getQuestionsDuration.add(questionsRequest.totalDurationMs, SCENARIO_TAGS);
    const questionsPayload = parseJson(questionsRes);
    const questionsOk = check(questionsRes, {
        'get questions status 200': (r) => r.status === 200,
    });
    getQuestionsStageSuccess.add(questionsOk ? 1 : 0, SCENARIO_TAGS);
    if (!questionsOk) {
        failSession('get_questions_failed', 'status=' + String(questionsRes.status));
        return;
    }

    const allQuestions = extractItems(questionsPayload);
    if (!allQuestions.length) {
        failSession('empty_questions');
        return;
    }

    const maxQuestions = QUESTIONS_PER_USER > 0 ? Math.min(QUESTIONS_PER_USER, allQuestions.length) : allQuestions.length;
    if (!SCENARIO.submitsAnswers || maxQuestions <= 0) {
        sessionSuccess.add(1, SCENARIO_TAGS);
        return;
    }

    const submitPhaseDelayTotalMs = Math.max(0, SUBMIT_PHASE_DELAY_MS) + staggerMsByVu(SUBMIT_PHASE_SPREAD_MS, vuNumber);
    maybeSleepMs(submitPhaseDelayTotalMs);

    const batchQueue = [];
    let batchQueuedAt = 0;

    for (let i = 0; i < maxQuestions; i++) {
        const question = allQuestions[i];
        const questionId = Number(question && question.id) || 0;
        if (questionId <= 0) {
            continue;
        }

        const answerPayload = buildAnswerPayload(question, i, vuNumber);
        if (answerPayload === null) {
            continue;
        }

        if (SCENARIO.batchSubmit) {
            if (!batchQueue.length) {
                batchQueuedAt = Date.now();
            }

            batchQueue.push({
                question_id: questionId,
                answer: answerPayload,
            });

            const shouldFlushBatch =
                batchQueue.length >= Math.max(1, BATCH_MAX_ITEMS) ||
                (Date.now() - batchQueuedAt) >= Math.max(0, BATCH_WINDOW_MS);

            if (shouldFlushBatch) {
                const batchResult = submitAnswerBatch(token, attemptId, batchQueue.slice());
                submitBatchStageSuccess.add(batchResult.ok ? 1 : 0, SCENARIO_TAGS);
                if (!batchResult.ok) {
                    const batchCode = batchResult.payload && (batchResult.payload.code || (batchResult.payload.data && batchResult.payload.data.code));
                    failSession('submit_answers_batch_failed', 'status=' + String(batchResult.status) + (batchCode ? ',code=' + String(batchCode) : ''));
                    return;
                }

                batchQueue.length = 0;
                batchQueuedAt = 0;
            }
        } else {
            const submitRes = apiRequest('POST', 'submit_answer', token, {
                attempt_id: attemptId,
                question_id: questionId,
                answer: answerPayload,
            });
            submitAnswerDuration.add(submitRes.timings.duration, SCENARIO_TAGS);
            const submitPayload = parseJson(submitRes);

            const submitOk = check(submitRes, {
                'submit answer status 200': (r) => r.status === 200,
            });
            submitSingleStageSuccess.add(submitOk ? 1 : 0, SCENARIO_TAGS);
            if (!submitOk) {
                const submitCode = submitPayload && (submitPayload.code || (submitPayload.data && submitPayload.data.code));
                failSession('submit_answer_failed', 'status=' + String(submitRes.status) + (submitCode ? ',code=' + String(submitCode) : ''));
                return;
            }
        }

        maybeThinkTime();
    }

    if (SCENARIO.batchSubmit && batchQueue.length > 0) {
        const finalBatchResult = submitAnswerBatch(token, attemptId, batchQueue.slice());
        submitBatchStageSuccess.add(finalBatchResult.ok ? 1 : 0, SCENARIO_TAGS);
        if (!finalBatchResult.ok) {
            const finalBatchCode = finalBatchResult.payload && (finalBatchResult.payload.code || (finalBatchResult.payload.data && finalBatchResult.payload.data.code));
            failSession('submit_answers_batch_failed', 'status=' + String(finalBatchResult.status) + (finalBatchCode ? ',code=' + String(finalBatchCode) : ''));
            return;
        }
    }

    if (SCENARIO.finishExam) {
        const finishRes = apiRequest('POST', 'finish_exam', token, {
            attempt_id: attemptId,
        });
        finishExamDuration.add(finishRes.timings.duration, SCENARIO_TAGS);
        const finishPayload = parseJson(finishRes);

        const finishOk = check(finishRes, {
            'finish exam status 200': (r) => r.status === 200,
        });
        finishExamStageSuccess.add(finishOk ? 1 : 0, SCENARIO_TAGS);
        if (!finishOk) {
            const finishCode = finishPayload && (finishPayload.code || (finishPayload.data && finishPayload.data.code));
            failSession('finish_exam_failed', 'status=' + String(finishRes.status) + (finishCode ? ',code=' + String(finishCode) : ''));
            return;
        }
    }

    sessionSuccess.add(1, SCENARIO_TAGS);
}
