/**
 * MediaLibraryModal — replacement for Gutenberg's editor.MediaUpload component.
 *
 * Registered via:
 *   addFilter('editor.MediaUpload', 'bladeberg/media-upload', () => MediaLibraryModal)
 *
 * Gutenberg calls this component with the following props:
 *   onSelect(attachment)  — call when the user has chosen a file
 *   allowedTypes          — array of MIME type prefixes the caller accepts
 *   multiple              — whether multiple files may be selected (currently single)
 *   render({ open })      — render prop that receives the "open modal" function
 *
 * The component itself renders nothing until render() returns a trigger element.
 * When the user clicks that element, the modal opens. Selecting a media item
 * calls onSelect and closes the modal.
 */
import React, { useCallback, useState } from 'react';
import { MediaGrid } from './MediaGrid.jsx';

/**
 * Simple inline modal using a <dialog> element.
 * Falls back gracefully when @wordpress/components Modal is unavailable
 * (avoids bundling the entire components package).
 */
function BbModal({ isOpen, onClose, title, children }) {
    if (!isOpen) return null;

    return (
        <div
            className="bb-media-modal-overlay"
            role="dialog"
            aria-modal="true"
            aria-label={title}
            onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
        >
            <div className="bb-media-modal">
                <div className="bb-media-modal__header">
                    <h2 className="bb-media-modal__title">{title}</h2>
                    <button
                        type="button"
                        className="bb-media-modal__close"
                        onClick={onClose}
                        aria-label="Close media library"
                    >
                        ✕
                    </button>
                </div>
                <div className="bb-media-modal__body">
                    {children}
                </div>
            </div>
        </div>
    );
}

/**
 * MediaLibraryModal — the editor.MediaUpload replacement.
 *
 * Registered via:
 *   addFilter('editor.MediaUpload', 'bladeberg/media-upload', () => MediaLibraryModal)
 *
 * Reads `window.BladebergConfig.mediaMode` to determine whether the upload
 * zone should be shown:
 *   'upload' → full library with upload drop-zone
 *   'select' → browse-only (no upload zone, no upload button)
 *
 * @param {Object}   props
 * @param {Function} props.onSelect
 * @param {string[]} [props.allowedTypes]
 * @param {boolean}  [props.multiple]
 * @param {Function} props.render
 */
export function MediaLibraryModal({ onSelect, allowedTypes = [], multiple = false, render }) {
    const [isOpen, setIsOpen] = useState(false);

    const mode        = window.BladebergConfig?.mediaMode ?? 'upload';
    const allowUpload = mode === 'upload';
    const title       = allowUpload ? 'BladeBerg Media Library' : 'BladeBerg Media — Browse';

    const open  = useCallback(() => setIsOpen(true), []);
    const close = useCallback(() => setIsOpen(false), []);

    const handleSelect = useCallback((attachment) => {
        onSelect(attachment);
        close();
    }, [onSelect, close]);

    // Derive MIME prefixes the grid filter can use (e.g. ['image'] from ['image/jpeg'])
    const mimeFilters = [...new Set(
        (allowedTypes ?? []).map((t) => t.split('/')[0]).filter(Boolean)
    )];

    return (
        <>
            {typeof render === 'function' && render({ open })}

            <BbModal
                isOpen={isOpen}
                onClose={close}
                title={title}
            >
                <MediaGrid
                    onSelect={handleSelect}
                    allowedTypes={mimeFilters}
                    allowUpload={allowUpload}
                />
            </BbModal>
        </>
    );
}
