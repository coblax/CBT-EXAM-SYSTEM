import './styles/main.css';
import { bootstrapFrontendApp } from './app';
import { getFrontendConfig } from './app/core/config.js';
import { registerServiceWorker } from './app/core/service-worker-registration.js';

registerServiceWorker(getFrontendConfig(window));

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapFrontendApp, { once: true });
} else {
    bootstrapFrontendApp();
}
