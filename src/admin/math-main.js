import { observeMathContainers } from '../shared/math-render';
import { bootAdminMathAuthoring } from './math-authoring';

function bootAdminMathPreview() {
    if (!document.body) {
        return;
    }

    observeMathContainers(document.body);
    bootAdminMathAuthoring();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAdminMathPreview, { once: true });
} else {
    bootAdminMathPreview();
}
