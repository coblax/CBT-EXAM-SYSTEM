export async function mountConfirmStage(context, options) {
    await context.loadLegacyRuntime((options && options.reason) || 'confirm-stage');
    return createLegacyHandoffController();
}

function createLegacyHandoffController() {
    return {
        render: function () {},
        unmount: function () {}
    };
}
