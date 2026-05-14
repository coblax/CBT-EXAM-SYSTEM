export function loadLegacyRuntimeModule() {
    return import('../legacy-runtime.js');
}

export function loadLoginStageModule() {
    return import('../stages/login-runtime.js');
}

export function loadConfirmStageModule() {
    return import('../stages/confirm-runtime.js');
}

export function loadExamStageModule() {
    return import('../stages/exam-runtime.js');
}

export function loadResultStageModule() {
    return import('../stages/result-runtime.js');
}

export function loadResultRendererModule() {
    return import('../stages/result.js');
}
