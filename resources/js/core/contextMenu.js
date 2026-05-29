/**
 * Restore the right-click -> block "Options" context menu.
 *
 * isolated-block-editor v2.30 (its final release) ships no context-menu feature
 * and exposes no wp.data store we could call to open one programmatically. We
 * emulate Gutenberg's behaviour with the DOM only:
 *
 *   1. On right-click inside a block ([data-block]) we suppress the browser's
 *      native menu and replay a left-button mousedown/mouseup on the target so
 *      the block-editor selects that block (selection renders the block toolbar).
 *   2. Once the toolbar mounts we click its existing "Options" (vertical dots)
 *      toggle, which already contains Gutenberg's full action list.
 *
 * Idempotent: the document-level listener is installed at most once.
 */
export function installBlockContextMenu() {
    if (window.__bbContextMenuInstalled) return;
    window.__bbContextMenuInstalled = true;

    const EDITOR_SCOPE =
        '.block-editor-block-list__layout, .editor-styles-wrapper, .iso-editor';

    function openOptionsMenu(attempt = 0) {
        const toggle =
            document.querySelector('.block-editor-block-settings-menu__toggle') ||
            document.querySelector('.block-editor-block-settings-menu button') ||
            document.querySelector('button[aria-label="Options"]') ||
            document.querySelector('.block-editor-block-settings-menu [role="button"]');

        if (toggle) {
            toggle.click();
            return;
        }
        if (attempt < 10) setTimeout(() => openOptionsMenu(attempt + 1), 30);
    }

    document.addEventListener(
        'contextmenu',
        (event) => {
            const target = event.target;
            const blockEl = target?.closest?.('[data-block]');
            if (!blockEl || !blockEl.closest(EDITOR_SCOPE)) {
                return;
            }

            event.preventDefault();

            const opts = { bubbles: true, cancelable: true, view: window, button: 0 };
            target.dispatchEvent(new MouseEvent('mousedown', opts));
            target.dispatchEvent(new MouseEvent('mouseup', opts));
            if (typeof blockEl.focus === 'function') {
                try { blockEl.focus({ preventScroll: true }); } catch (_) {}
            }

            setTimeout(() => openOptionsMenu(), 30);
        },
        { capture: true }
    );
}
