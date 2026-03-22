import './styles/main.css';
import { bootstrapFrontendApp } from './app';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapFrontendApp, { once: true });
} else {
    bootstrapFrontendApp();
}
