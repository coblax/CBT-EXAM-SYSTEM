var MATH_SELECTOR = '.cbt-math[data-cbt-math]';

function hasMathMarkup(container) {
    if (!container) {
        return false;
    }

    if (typeof container.matches === 'function' && container.matches(MATH_SELECTOR)) {
        return true;
    }

    return typeof container.querySelector === 'function'
        && container.querySelector(MATH_SELECTOR) !== null;
}

export function createLazyMathEnhancer(deps) {
    deps = deps || {};

    var importMathRenderer = typeof deps.importMathRenderer === 'function'
        ? deps.importMathRenderer
        : function () {
            return import('../../../shared/math-render.js');
        };
    var recordTimeline = typeof deps.recordTimeline === 'function'
        ? deps.recordTimeline
        : null;
    var root = deps.root || null;
    var mathRendererModule = null;
    var mathRendererPromise = null;

    function recordLazyMathTimeline(kind, summary, meta) {
        if (!recordTimeline) {
            return;
        }

        recordTimeline(kind, summary, Object.assign({
            stage: String(deps.stage || '')
        }, meta || {}));
    }

    function loadMathRenderer() {
        if (mathRendererModule && typeof mathRendererModule.renderMathInContainer === 'function') {
            return Promise.resolve(mathRendererModule);
        }

        if (!mathRendererPromise) {
            recordLazyMathTimeline('chunk:math:load:start', 'Memuat runtime math KaTeX.', {
                target: 'math-render'
            });

            mathRendererPromise = Promise.resolve()
                .then(importMathRenderer)
                .then(function (module) {
                    if (!module || typeof module.renderMathInContainer !== 'function') {
                        throw new Error('Math renderer module tidak valid.');
                    }

                    mathRendererModule = module;
                    recordLazyMathTimeline('chunk:math:load:success', 'Runtime math KaTeX siap.', {
                        target: 'math-render'
                    });
                    return mathRendererModule;
                })
                .catch(function (error) {
                    recordLazyMathTimeline('chunk:math:load:error', 'Runtime math KaTeX gagal dimuat.', {
                        target: 'math-render',
                        error: error instanceof Error ? {
                            message: String(error.message || '')
                        } : null
                    });
                    throw error;
                })
                .finally(function () {
                    mathRendererPromise = null;
                });
        }

        return mathRendererPromise;
    }

    return function enhanceRichMath(container) {
        var target = container || root;
        if (!hasMathMarkup(target)) {
            return Promise.resolve(0);
        }

        return loadMathRenderer()
            .then(function (module) {
                return module.renderMathInContainer(target);
            })
            .catch(function () {
                return 0;
            });
    };
}
