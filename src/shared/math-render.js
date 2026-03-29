import katex from 'katex';
import 'katex/dist/katex.min.css';
import './math-render.css';

var MATH_SELECTOR = '.cbt-math[data-cbt-math]';

function collectMathNodes(container) {
    if (!container) {
        return [];
    }

    var nodes = [];
    if (typeof container.matches === 'function' && container.matches(MATH_SELECTOR)) {
        nodes.push(container);
    }

    if (typeof container.querySelectorAll === 'function') {
        nodes = nodes.concat(Array.from(container.querySelectorAll(MATH_SELECTOR)));
    }

    return nodes;
}

function normalizeDisplayMode(value) {
    return String(value || '').toLowerCase() === 'block' ? 'block' : 'inline';
}

function renderMathNode(node) {
    if (!(node instanceof HTMLElement)) {
        return false;
    }

    var source = String(node.getAttribute('data-cbt-math') || '').trim();
    if (source === '') {
        return false;
    }

    var displayMode = normalizeDisplayMode(node.getAttribute('data-cbt-math-display'));
    var renderKey = source + '::' + displayMode;

    if (node.dataset.cbtMathRenderKey === renderKey && node.classList.contains('is-katex-rendered')) {
        return false;
    }

    if (!Object.prototype.hasOwnProperty.call(node, '__cbtMathFallbackHtml')) {
        node.__cbtMathFallbackHtml = node.innerHTML;
    }

    try {
        katex.render(source, node, {
            displayMode: displayMode === 'block',
            output: 'htmlAndMathml',
            strict: 'ignore',
            throwOnError: true,
            trust: false,
        });

        node.dataset.cbtMathRenderKey = renderKey;
        node.classList.remove('is-katex-fallback');
        node.classList.add('is-katex-rendered');
        return true;
    } catch (error) {
        if (typeof node.__cbtMathFallbackHtml === 'string') {
            node.innerHTML = node.__cbtMathFallbackHtml;
        }

        node.dataset.cbtMathRenderKey = '';
        node.classList.remove('is-katex-rendered');
        node.classList.add('is-katex-fallback');
        return false;
    }
}

export function renderMathInContainer(container) {
    return collectMathNodes(container).reduce(function (count, node) {
        return count + (renderMathNode(node) ? 1 : 0);
    }, 0);
}

export function observeMathContainers(root) {
    var target = root instanceof Document
        ? (root.body || root.documentElement)
        : root;

    if (!(target instanceof HTMLElement)) {
        return null;
    }

    renderMathInContainer(target);

    var ownerWindow = target.ownerDocument && target.ownerDocument.defaultView
        ? target.ownerDocument.defaultView
        : (typeof window !== 'undefined' ? window : null);
    var MutationObserverRef = ownerWindow && ownerWindow.MutationObserver
        ? ownerWindow.MutationObserver
        : null;

    if (typeof MutationObserverRef !== 'function') {
        return null;
    }

    var observer = new MutationObserverRef(function (mutations) {
        mutations.forEach(function (mutation) {
            Array.from(mutation.addedNodes || []).forEach(function (node) {
                if (!(node instanceof HTMLElement)) {
                    return;
                }
                renderMathInContainer(node);
            });
        });
    });

    observer.observe(target, {
        childList: true,
        subtree: true,
    });

    return observer;
}
