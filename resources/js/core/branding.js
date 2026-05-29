/**
 * Editor UI branding: replace visible "WordPress" / "wp:" text with the
 * BladeBerg brand and configured block prefix throughout the editor chrome.
 *
 * TEXTAREA and INPUT elements are intentionally excluded — editing them would
 * corrupt the stored block markup that the server parser depends on. The Code
 * Editor mode's raw-markup textarea gets a separate read-only overlay (see
 * installCodeEditorOverlay) so users see the branded prefix without the
 * underlying stored value being touched.
 *
 * Both installers are idempotent and global; calling them more than once (e.g.
 * mounting multiple editors) only attaches a single set of observers.
 */

function resolvePrefix(prefix) {
    return prefix ?? window.BladebergConfig?.blockPrefix ?? 'bb';
}

/**
 * Replace "WordPress" / "wp:" / "core/" text under the document with the
 * BladeBerg brand and configured prefix.
 *
 * @param {string} [prefix]
 */
export function applyBranding(prefix) {
    const activePrefix = resolvePrefix(prefix);

    if (window.__bbBrandingInstalled) {
        // Re-scan once for content that mounted before this call.
        patchTextNodes(document.body, activePrefix);
        return;
    }
    window.__bbBrandingInstalled = true;

    patchTextNodes(document.body, activePrefix);

    const observer = new MutationObserver((mutations) => {
        for (const { addedNodes } of mutations) {
            for (const node of addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) patchTextNodes(node, activePrefix);
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    window.Bladeberg = window.Bladeberg ?? {};
    window.Bladeberg._brandingObserver = observer;

    installCodeEditorOverlay(activePrefix);
}

function buildReplacements(prefix) {
    return [
        { from: /WordPress/g, to: 'BladeBerg' },
        { from: /\bWP\b(?![-_])/g, to: 'BB' },
        { from: /\bwp:([\w-]+)/g, to: `${prefix}:$1` },
        { from: /\bcore\/([\w-]+)/g, to: `${prefix}/$1` },
    ];
}

function patchTextNodes(root, prefix) {
    if (!root) return;

    const replacements = buildReplacements(prefix);

    if (root.nodeType === Node.TEXT_NODE) {
        applyToTextNode(root, replacements);
        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const tag = node.parentElement?.tagName ?? '';
            if (['SCRIPT', 'STYLE', 'INPUT', 'TEXTAREA'].includes(tag)) {
                return NodeFilter.FILTER_REJECT;
            }
            const hasMatch = replacements.some((r) => {
                r.from.lastIndex = 0;
                return r.from.test(node.textContent);
            });
            return hasMatch ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
        },
    });

    const nodes = [];
    let n;
    while ((n = walker.nextNode())) nodes.push(n);
    nodes.forEach((node) => applyToTextNode(node, replacements));
}

function applyToTextNode(node, replacements) {
    let text = node.textContent;
    for (const { from, to } of replacements) {
        from.lastIndex = 0;
        text = text.replace(from, to);
    }
    if (text !== node.textContent) node.textContent = text;
}

/**
 * Layer a read-only <pre> over the Code Editor's raw-markup textarea showing the
 * branded prefix, without mutating the textarea's real (wp:) value.
 *
 * @param {string} [prefix]
 */
export function installCodeEditorOverlay(prefix) {
    const activePrefix = resolvePrefix(prefix);
    const OVERLAY_ATTR = 'data-bb-code-overlay';

    function brandRawMarkup(raw) {
        return raw
            .replace(/wp:([\w-]+)/g, `${activePrefix}:$1`)
            .replace(/WordPress/g, 'BladeBerg')
            .replace(/core\/([\w-]+)/g, `${activePrefix}/$1`);
    }

    function attachOverlay(textarea) {
        if (textarea.hasAttribute(OVERLAY_ATTR)) return;
        textarea.setAttribute(OVERLAY_ATTR, '1');

        const parent = textarea.parentElement;
        if (parent && getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
        }

        const pre = document.createElement('pre');
        pre.className = 'bb-code-editor-overlay';
        pre.textContent = brandRawMarkup(textarea.value);
        pre.setAttribute('aria-hidden', 'true');

        textarea.parentNode?.insertBefore(pre, textarea.nextSibling);

        textarea.addEventListener('input', () => {
            pre.textContent = brandRawMarkup(textarea.value);
        });
    }

    const SELECTOR = '.editor-post-text-editor, .block-editor-plain-text';
    document.querySelectorAll(SELECTOR).forEach(attachOverlay);

    if (window.__bbOverlayInstalled) return;
    window.__bbOverlayInstalled = true;

    const overlayObserver = new MutationObserver((mutations) => {
        for (const { addedNodes } of mutations) {
            for (const node of addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;
                node.querySelectorAll?.(SELECTOR).forEach(attachOverlay);
                if (node.matches?.(SELECTOR)) attachOverlay(node);
            }
        }
    });
    overlayObserver.observe(document.body, { childList: true, subtree: true });

    window.Bladeberg = window.Bladeberg ?? {};
    window.Bladeberg._overlayObserver = overlayObserver;
}
