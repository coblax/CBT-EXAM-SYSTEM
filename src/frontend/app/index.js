import { getFrontendConfig } from './core/config';

export async function bootstrapFrontendApp() {
    var config = getFrontendConfig(window);

    if (String(config.frontendMode || 'student') === 'supervisor') {
        const module = await import('./supervisor/runtime.js');
        return module.bootstrapSupervisorApp();
    }

    const module = await import('./runtime.js');
    return module.bootstrapFrontendApp();
}
