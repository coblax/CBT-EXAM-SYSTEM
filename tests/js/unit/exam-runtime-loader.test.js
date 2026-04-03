import { afterEach, describe, expect, it, vi } from 'vitest';
import { createExamRuntimeLoader } from '../../../src/frontend/app/core/exam-runtime-loader.js';

afterEach(function () {
    vi.restoreAllMocks();
});

describe('createExamRuntimeLoader', function () {
    it('loads the runtime bundle once and reuses the cached bundle', async function () {
        var importer = vi.fn(function () {
            return Promise.resolve({
                label: 'bundle'
            });
        });
        var instantiateBundle = vi.fn(function (module) {
            return {
                loaded: module.label
            };
        });
        var loader = createExamRuntimeLoader({
            importRuntimeBundle: importer,
            instantiateBundle: instantiateBundle
        });

        var first = await loader.ensure();
        var second = await loader.ensure();

        expect(first).toBe(second);
        expect(first.loaded).toBe('bundle');
        expect(importer).toHaveBeenCalledTimes(1);
        expect(instantiateBundle).toHaveBeenCalledTimes(1);
    });

    it('retries after a failed prefetch without leaking the failure', async function () {
        var importer = vi.fn()
            .mockRejectedValueOnce(new Error('prefetch failed'))
            .mockResolvedValueOnce({
                label: 'bundle'
            });
        var loader = createExamRuntimeLoader({
            importRuntimeBundle: importer,
            instantiateBundle: function (module) {
                return {
                    loaded: module.label
                };
            }
        });

        loader.prefetch();
        await Promise.resolve();
        await Promise.resolve();

        await expect(loader.ensure()).resolves.toMatchObject({
            loaded: 'bundle'
        });
        expect(importer).toHaveBeenCalledTimes(2);
    });
});
