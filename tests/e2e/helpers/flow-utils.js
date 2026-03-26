async function waitForCondition(callback, options = {}) {
    const timeoutMs = Math.max(500, Number(options.timeoutMs) || 15000);
    const intervalMs = Math.max(50, Number(options.intervalMs) || 250);
    const errorMessage = String(options.errorMessage || 'Timed out waiting for condition.');
    const startedAt = Date.now();
    let lastError = null;

    while ((Date.now() - startedAt) < timeoutMs) {
        try {
            const result = await callback();
            if (result) {
                return result;
            }
        } catch (error) {
            lastError = error;
        }

        await new Promise((resolve) => setTimeout(resolve, intervalMs));
    }

    if (lastError instanceof Error && lastError.message) {
        throw new Error(`${errorMessage} Last error: ${lastError.message}`);
    }

    throw new Error(errorMessage);
}

module.exports = {
    waitForCondition,
};
