export async function mountExamStage(context, options) {
    await context.loadLegacyRuntime((options && options.reason) || 'exam-stage');
    return createLegacyHandoffController();
}

function createLegacyHandoffController() {
    return {
        render: function () {},
        unmount: function () {}
    };
}
