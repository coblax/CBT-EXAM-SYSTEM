import { afterEach, describe, expect, it, vi } from 'vitest';
import { createLazyMathEnhancer } from '../../../src/frontend/app/core/lazy-math.js';

afterEach(function () {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('createLazyMathEnhancer', function () {
    it('does not load the math renderer when the container has no math markup', async function () {
        document.body.innerHTML = '<div id="fixture"><p>Tidak ada math.</p></div>';
        var importer = vi.fn(function () {
            return Promise.resolve({
                renderMathInContainer: function () {
                    return 99;
                }
            });
        });
        var enhancer = createLazyMathEnhancer({
            importMathRenderer: importer
        });

        var renderedCount = await enhancer(document.getElementById('fixture'));

        expect(renderedCount).toBe(0);
        expect(importer).not.toHaveBeenCalled();
    });

    it('loads the math renderer lazily and reuses the loaded module', async function () {
        document.body.innerHTML = '<div id="fixture"><span class="cbt-math" data-cbt-math="x^{2}" data-cbt-math-display="inline">x^2</span></div>';

        var renderMathInContainer = vi.fn(function (container) {
            return container.querySelectorAll('.cbt-math[data-cbt-math]').length;
        });
        var importer = vi.fn(function () {
            return Promise.resolve({
                renderMathInContainer: renderMathInContainer
            });
        });
        var enhancer = createLazyMathEnhancer({
            importMathRenderer: importer
        });
        var root = document.getElementById('fixture');

        expect(await enhancer(root)).toBe(1);
        root.insertAdjacentHTML('beforeend', '<span class="cbt-math" data-cbt-math="y^{2}" data-cbt-math-display="inline">y^2</span>');
        expect(await enhancer(root)).toBe(2);

        expect(importer).toHaveBeenCalledTimes(1);
        expect(renderMathInContainer).toHaveBeenCalledTimes(2);
    });
});
