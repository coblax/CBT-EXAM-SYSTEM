import { describe, expect, it, vi } from 'vitest';
import {
    createQuestionMediaPrewarmManager,
    extractQuestionMediaUrlsFromPayload
} from '../../../src/frontend/app/exam/media-prewarm.js';

function createWindowRef(overrides = {}) {
    var postMessage = vi.fn();
    return {
        location: {
            href: 'https://school.test/cbt/exam'
        },
        navigator: {
            serviceWorker: {
                controller: {
                    postMessage: postMessage
                }
            }
        },
        postMessage: postMessage,
        ...overrides
    };
}

describe('media prewarm helpers', function () {
    it('extracts same-origin media URLs from rich HTML and explicit payload fields', function () {
        var windowRef = createWindowRef();
        var urls = extractQuestionMediaUrlsFromPayload({
            text: '<p><img src="/uploads/q1.png" srcset="/uploads/q1-small.webp 1x, /uploads/q1-large.webp 2x"></p>',
            audio_url: 'https://school.test/uploads/audio.mp3',
            option: {
                content: '<video poster="/uploads/poster.jpg"><source src="/uploads/video.mp4"></video>',
                ignored: 'https://cdn.test/uploads/remote.png'
            }
        }, windowRef);

        expect(urls).toEqual([
            'https://school.test/uploads/q1.png',
            'https://school.test/uploads/q1-small.webp',
            'https://school.test/uploads/q1-large.webp',
            'https://school.test/uploads/audio.mp3',
            'https://school.test/uploads/poster.jpg',
            'https://school.test/uploads/video.mp4'
        ]);
    });

    it('posts deduplicated same-origin media URLs to the Service Worker', function () {
        var windowRef = createWindowRef();
        var manager = createQuestionMediaPrewarmManager({
            windowRef: windowRef
        });

        var sent = manager.prewarmQuestionMedia([
            { text: '<img src="/uploads/q1.png">' },
            { text: '<img src="/uploads/q1.png"><img src="https://cdn.test/q2.png">' }
        ], {
            attemptId: 44,
            reason: 'test'
        });

        expect(sent).toEqual(['https://school.test/uploads/q1.png']);
        expect(windowRef.navigator.serviceWorker.controller.postMessage).toHaveBeenCalledWith({
            type: 'CBT_PRECACHE_MEDIA_URLS',
            urls: ['https://school.test/uploads/q1.png']
        });
        expect(manager.getPrewarmedUrlCount()).toBe(1);
    });

    it('does nothing when no Service Worker controller is available', function () {
        var windowRef = createWindowRef({
            navigator: {
                serviceWorker: {}
            }
        });
        var manager = createQuestionMediaPrewarmManager({
            windowRef: windowRef
        });

        expect(manager.prewarmQuestionMedia({ text: '<img src="/uploads/q1.png">' }, { attemptId: 44 })).toEqual([]);
    });
});
