import { createAuthenticatedStageRuntime } from './authenticated-runtime.js';

export async function mountConfirmStage(context, options) {
    options = options || {};

    var runtime = createAuthenticatedStageRuntime(context, {
        onAction: handleConfirmAction,
        onInput: handleConfirmInput,
        renderConfirmStage: function (activeRuntime) {
            return activeRuntime.authStageManager.renderConfirmStage();
        },
        stage: 'confirm'
    });

    if (!runtime.state.token || !runtime.state.user) {
        await context.transitionTo('login', { reason: 'missing-auth-confirm' });
        return runtime;
    }

    runtime.state.busy = true;
    runtime.clearMessages();
    runtime.render('confirm-load-start');

    try {
        await runtime.loadExams({
            suppressAuthExpiry: options.suppressAuthExpiry === true
        });
    } catch (error) {
        runtime.state.error = error instanceof Error ? error.message : 'Gagal memuat daftar ujian.';
    } finally {
        runtime.state.busy = false;
        if (runtime.state.authProgressMode === 'login') {
            runtime.resetAuthProgressState();
        }
        runtime.render('confirm-load-complete');
    }

    return runtime;

    async function handleConfirmAction(action, target, activeRuntime, event) {
        if (action === 'reload-exams') {
            event.preventDefault();
            await reloadExams(activeRuntime);
            return;
        }

        if (action === 'select-exam' || action === 'select-exam-mobile') {
            event.preventDefault();
            activeRuntime.authStageManager.updateSelectedExam(target.getAttribute('data-id'));
            return;
        }

        if (action === 'toggle-exam-picker-mobile') {
            event.preventDefault();
            activeRuntime.state.examPickerMobileOpen = !activeRuntime.state.examPickerMobileOpen;
            activeRuntime.render('toggle-exam-picker-mobile');
            return;
        }

        if (action === 'view-result') {
            event.preventDefault();
            await viewResult(activeRuntime);
            return;
        }

        if (action === 'start-exam') {
            event.preventDefault();
            await startExam(activeRuntime);
        }
    }

    function handleConfirmInput(event, activeRuntime) {
        var target = event && event.target ? event.target : null;
        if (!target || String(target.name || '') !== 'exam_token') {
            return;
        }

        var normalized = activeRuntime.normalizeExamToken(target.value);
        activeRuntime.state.examToken = normalized;
        if (target.value !== normalized) {
            target.value = normalized;
        }
    }

    async function reloadExams(activeRuntime) {
        if (activeRuntime.state.busy) {
            return;
        }

        activeRuntime.state.busy = true;
        activeRuntime.clearMessages();
        activeRuntime.render('reload-exams-start');

        try {
            await activeRuntime.loadExams();
            activeRuntime.state.success = 'Daftar ujian diperbarui.';
        } catch (error) {
            activeRuntime.state.error = error instanceof Error ? error.message : 'Gagal memperbarui daftar ujian.';
        } finally {
            activeRuntime.state.busy = false;
            activeRuntime.render('reload-exams-complete');
        }
    }

    async function viewResult(activeRuntime) {
        if (activeRuntime.state.busy) {
            return;
        }

        var selectedExam = activeRuntime.getSelectedExam();
        if (!selectedExam) {
            activeRuntime.state.error = 'Pilih exam terlebih dahulu.';
            activeRuntime.render('view-result-missing-selection');
            return;
        }

        activeRuntime.persistAuthSession();
        await context.transitionTo('result', {
            reason: 'view-result',
            selectedExamId: Number(selectedExam.id) || Number(activeRuntime.state.selectedExamId) || 0
        });
    }

    async function startExam(activeRuntime) {
        if (activeRuntime.state.busy) {
            return;
        }

        var selectedExam = activeRuntime.getSelectedExam();
        if (!selectedExam) {
            activeRuntime.state.error = 'Pilih exam terlebih dahulu.';
            activeRuntime.render('start-exam-missing-selection');
            return;
        }

        activeRuntime.clearMessages();
        await activeRuntime.handoffToLegacyStartExam({
            reason: 'start-exam',
            selectedExam: selectedExam
        });
    }
}
