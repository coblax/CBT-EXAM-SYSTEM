export async function mountResultStage(context, options) {
    await context.loadLegacyRuntime((options && options.reason) || 'result-stage');
    return createLegacyHandoffController();
}

function createLegacyHandoffController() {
    return {
        render: function () {},
        unmount: function () {}
    };
}
