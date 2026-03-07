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

const BASE_URL = envString('BASE_URL', 'http://127.0.0.1').replace(/\/+$/, '');
const API_BASE = BASE_URL + '/wp-json/cbt/v1';
const EXAM_ID = envNumber('EXAM_ID', 0);
const EXAM_TOKEN = envString('EXAM_TOKEN', '').trim().toUpperCase();
const VUS = envNumber('VUS', 1000);
const ITERATIONS = envNumber('ITERATIONS', 1);
const MAX_DURATION = envString('MAX_DURATION', '45m');
const REQUEST_TIMEOUT = envString('REQUEST_TIMEOUT', defaultRequestTimeout(VUS));
const QUESTIONS_PER_USER = envNumber('QUESTIONS_PER_USER', 0);
const FINISH_EXAM = envBool('FINISH_EXAM', true);
const THINK_MIN_MS = envNumber('THINK_MIN_MS', 100);
const THINK_MAX_MS = envNumber('THINK_MAX_MS', 250);
const SESSION_START_SPREAD_MS = envNumber('SESSION_START_SPREAD_MS', defaultSessionStartSpreadMs(VUS));
const POST_START_SPREAD_MS = envNumber('POST_START_SPREAD_MS', defaultPostStartSpreadMs(VUS));
const SUBMIT_PHASE_DELAY_MS = envNumber('SUBMIT_PHASE_DELAY_MS', 0);
const SUBMIT_PHASE_SPREAD_MS = envNumber('SUBMIT_PHASE_SPREAD_MS', 0);
const SUBMIT_MODE = envString('SUBMIT_MODE', 'all').trim().toLowerCase();
const ENABLE_BATCH_SUBMIT = envBool('ENABLE_BATCH_SUBMIT', true);
const BATCH_WINDOW_MS = envNumber('BATCH_WINDOW_MS', 2500);
const BATCH_MAX_ITEMS = envNumber('BATCH_MAX_ITEMS', 20);
const STRICT_EXAM_ID = envBool('STRICT_EXAM_ID', false);
const ENABLE_THRESHOLDS = envBool('ENABLE_THRESHOLDS', true);
const DEBUG_LOG = envBool('DEBUG_LOG', false);
const DEBUG_VU_LIMIT = envNumber('DEBUG_VU_LIMIT', 3);
const SKIP_EXAMS_REQUEST = envBool('SKIP_EXAMS_REQUEST', EXAM_ID > 0);
const LOGIN_RETRIES = Math.max(0, envNumber('LOGIN_RETRIES', 0));
const EXAMS_RETRIES = Math.max(0, envNumber('EXAMS_RETRIES', 0));
const START_ATTEMPT_RETRIES = Math.max(0, envNumber('START_ATTEMPT_RETRIES', defaultRetryCount(VUS)));
const GET_QUESTIONS_RETRIES = Math.max(0, envNumber('GET_QUESTIONS_RETRIES', defaultRetryCount(VUS)));
const RETRY_BACKOFF_MS = Math.max(250, envNumber('RETRY_BACKOFF_MS', 1500));
const USERS_FILE = './students.json';

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
    cbt_submit_answer_duration: ['p(95)<900'],
    cbt_submit_answers_batch_duration: ['p(95)<700'],
};

export const options = {
    scenarios: {
        exam_sessions: {
            executor: 'per-vu-iterations',
            vus: VUS,
            iterations: ITERATIONS,
            maxDuration: MAX_DURATION,
        },
    },
    thresholds: ENABLE_THRESHOLDS ? defaultThresholds : {},
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

if (VUS >= 200 && EXAM_ID <= 0) {
    console.warn('[k6] EXAM_ID belum diisi. Untuk load test tinggi, set EXAM_ID agar semua VU menembak exam yang sama dan bisa mengurangi request /exams.');
}

if (users.length < VUS) {
    console.warn('[k6] Jumlah akun siswa (' + String(users.length) + ') lebih kecil dari VUS (' + String(VUS) + '). Script akan reuse akun.');
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
    sessionFailure.add(1, { reason: reason });
    sessionSuccess.add(0);
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
        requestRetries.add(1, { endpoint: requestLabel });
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
    submitAnswersBatchDuration.add(batchRes.timings.duration);
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
    loginDuration.add(loginRequest.totalDurationMs);
    const loginBody = parseJson(loginRes);
    const loginOk = check(loginRes, {
        'login status 200': (r) => r.status === 200,
        'login token exists': () => !!(loginBody && loginBody.token),
    });
    if (!loginOk) {
        failSession('login_failed', 'status=' + String(loginRes.status));
        return;
    }

    const token = String(loginBody.token || '');
    let selectedExam = null;
    if (EXAM_ID > 0 && SKIP_EXAMS_REQUEST) {
        selectedExam = { id: EXAM_ID };
    } else {
        const examsRequest = apiRequestWithRetry('GET', 'exams', token, null, EXAMS_RETRIES, 'exams');
        const examsRes = examsRequest.response;
        const examsBody = parseJson(examsRes);
        const examsOk = check(examsRes, {
            'get exams status 200': (r) => r.status === 200,
        });
        if (!examsOk) {
            failSession('get_exams_failed', 'status=' + String(examsRes.status));
            return;
        }

        const examItems = extractItems(examsBody);
        if (!examItems.length) {
            failSession('empty_exam_list');
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
    }

    const startBody = {
        exam_id: Number(selectedExam.id) || 0,
    };
    if (EXAM_TOKEN !== '') {
        startBody.exam_token = EXAM_TOKEN;
    }

    const startRequest = apiRequestWithRetry('POST', 'start_attempt', token, startBody, START_ATTEMPT_RETRIES, 'start_attempt');
    const startRes = startRequest.response;
    startAttemptDuration.add(startRequest.totalDurationMs);
    const startPayload = parseJson(startRes);
    const startOk = check(startRes, {
        'start attempt status 200': (r) => r.status === 200,
        'start attempt id exists': () => !!(startPayload && Number(startPayload.attempt_id) > 0),
    });
    if (!startOk) {
        const startCode = startPayload && (startPayload.code || (startPayload.data && startPayload.data.code));
        failSession('start_attempt_failed', 'status=' + String(startRes.status) + (startCode ? ',code=' + String(startCode) : ''));
        return;
    }

    const attemptId = Number(startPayload.attempt_id) || 0;
    const examId = Number(selectedExam.id) || 0;
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
    getQuestionsDuration.add(questionsRequest.totalDurationMs);
    const questionsPayload = parseJson(questionsRes);
    const questionsOk = check(questionsRes, {
        'get questions status 200': (r) => r.status === 200,
    });
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
    if (SUBMIT_MODE === 'none' || maxQuestions <= 0) {
        sessionSuccess.add(1);
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

        if (ENABLE_BATCH_SUBMIT) {
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
            submitAnswerDuration.add(submitRes.timings.duration);
            const submitPayload = parseJson(submitRes);

            const submitOk = check(submitRes, {
                'submit answer status 200': (r) => r.status === 200,
            });
            if (!submitOk) {
                const submitCode = submitPayload && (submitPayload.code || (submitPayload.data && submitPayload.data.code));
                failSession('submit_answer_failed', 'status=' + String(submitRes.status) + (submitCode ? ',code=' + String(submitCode) : ''));
                return;
            }
        }

        maybeThinkTime();
    }

    if (ENABLE_BATCH_SUBMIT && batchQueue.length > 0) {
        const finalBatchResult = submitAnswerBatch(token, attemptId, batchQueue.slice());
        if (!finalBatchResult.ok) {
            const finalBatchCode = finalBatchResult.payload && (finalBatchResult.payload.code || (finalBatchResult.payload.data && finalBatchResult.payload.data.code));
            failSession('submit_answers_batch_failed', 'status=' + String(finalBatchResult.status) + (finalBatchCode ? ',code=' + String(finalBatchCode) : ''));
            return;
        }
    }

    if (FINISH_EXAM) {
        const finishRes = apiRequest('POST', 'finish_exam', token, {
            attempt_id: attemptId,
        });
        finishExamDuration.add(finishRes.timings.duration);
        const finishPayload = parseJson(finishRes);

        const finishOk = check(finishRes, {
            'finish exam status 200': (r) => r.status === 200,
        });
        if (!finishOk) {
            const finishCode = finishPayload && (finishPayload.code || (finishPayload.data && finishPayload.data.code));
            failSession('finish_exam_failed', 'status=' + String(finishRes.status) + (finishCode ? ',code=' + String(finishCode) : ''));
            return;
        }
    }

    sessionSuccess.add(1);
}
