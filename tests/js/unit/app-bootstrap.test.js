import { describe, expect, it } from 'vitest';
import { mountFrontendAppRuntime } from '../../../src/frontend/app/core/app-bootstrap.js';

function createNoopEventManager() {
    return {
        handleChange: function () {},
        handleDocumentClick: function () {},
        handleInput: function () {},
        handleKeydown: function () {},
        handlePointerDown: function () {
            return false;
        },
        handleRootClick: function () {
            return false;
        },
        handleSubmit: function () {}
    };
}

describe('mountFrontendAppRuntime profile photo fallback', function () {
    it('shows fallback initials when profile photo image fails to load', function () {
        document.body.innerHTML = [
            '<div id="root">',
            '<button type="button">',
            '<img data-cbt-profile-photo="confirm" src="https://old-domain.test/wp-content/uploads/missing.jpg" alt="Ayu" />',
            '<span data-cbt-profile-photo-fallback hidden>A</span>',
            '</button>',
            '</div>'
        ].join('');

        var root = document.getElementById('root');
        var image = root.querySelector('[data-cbt-profile-photo]');
        var fallback = root.querySelector('[data-cbt-profile-photo-fallback]');

        mountFrontendAppRuntime({
            appEventManager: createNoopEventManager(),
            debugManager: null,
            documentRef: document,
            examSecurityManager: {
                mountSecurityListeners: function () {}
            },
            idleDetectionManager: {
                mountIdleListeners: function () {}
            },
            lifecycleManager: {
                mountLifecycleListeners: function () {}
            },
            root: root
        });

        image.dispatchEvent(new Event('error'));

        expect(image.hidden).toBe(true);
        expect(image.getAttribute('data-cbt-profile-photo-error')).toBe('1');
        expect(fallback.hidden).toBe(false);
    });
});
