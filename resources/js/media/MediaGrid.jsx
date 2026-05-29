/**
 * MediaGrid — paginated media browser component.
 *
 * Fetches GET /bladeberg/media with search + type filters and renders a
 * responsive thumbnail grid. Supports infinite-scroll-style "Load more"
 * and emits the selected attachment via props.onSelect.
 */
import React, { useCallback, useEffect, useRef, useState } from 'react';

const MIME_FILTERS = [
    { label: 'All',       value: '' },
    { label: 'Images',    value: 'image' },
    { label: 'Video',     value: 'video' },
    { label: 'Audio',     value: 'audio' },
    { label: 'Documents', value: 'application' },
];

/**
 * Fetch a page of media from the BladeBerg API.
 *
 * @param {Object} params  { page, perPage, search, mediaType }
 * @returns {Promise<{items: Array, total: number, totalPages: number}>}
 */
async function fetchMedia({ page = 1, perPage = 20, search = '', mediaType = '' } = {}) {
    const cfg       = window.BladebergConfig ?? {};
    const csrfToken = cfg.csrfToken ?? document.querySelector('meta[name="csrf-token"]')?.content;
    const base      = cfg.mediaApiUrl ?? '/bladeberg/media';

    const params = new URLSearchParams({ page, per_page: perPage });
    if (search)    params.set('search', search);
    if (mediaType) params.set('media_type', mediaType);

    const res = await fetch(`${base}?${params}`, {
        headers: {
            'Accept': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        credentials: 'same-origin',
    });

    if (!res.ok) throw new Error(`[BladeBerg] Media fetch failed: ${res.status}`);

    const items      = await res.json();
    const total      = parseInt(res.headers.get('X-WP-Total') ?? '0', 10);
    const totalPages = parseInt(res.headers.get('X-WP-TotalPages') ?? '1', 10);

    return { items, total, totalPages };
}

/**
 * Thumbnail cell for a single media item.
 */
function MediaItem({ item, isSelected, onSelect }) {
    const isImage = item.mime_type?.startsWith('image/');
    const url     = item.source_url ?? item.url ?? '';

    return (
        <button
            type="button"
            className={`bb-media-grid__item${isSelected ? ' bb-media-grid__item--selected' : ''}`}
            onClick={() => onSelect(item)}
            title={item.title?.rendered ?? item.title ?? url}
            aria-pressed={isSelected}
        >
            {isImage ? (
                <img
                    src={item.media_details?.sizes?.thumbnail?.source_url ?? url}
                    alt={item.alt_text ?? ''}
                    loading="lazy"
                    className="bb-media-grid__thumb"
                />
            ) : (
                <div className="bb-media-grid__icon" aria-hidden="true">
                    {mimeIcon(item.mime_type)}
                </div>
            )}
            <span className="bb-media-grid__name">
                {item.title?.rendered ?? item.title ?? ''}
            </span>
        </button>
    );
}

function mimeIcon(mimeType = '') {
    if (mimeType.startsWith('video/')) return '🎬';
    if (mimeType.startsWith('audio/')) return '🎵';
    if (mimeType.includes('pdf'))      return '📄';
    return '📁';
}

/**
 * Inline upload zone inside the grid.
 */
function UploadZone({ onUploaded }) {
    const inputRef  = useRef(null);
    const [dragging, setDragging] = useState(false);
    const [uploading, setUploading] = useState(false);

    const handleFiles = useCallback(async (files) => {
        if (!files.length) return;
        setUploading(true);

        try {
            const { bladebergMediaUpload } = await import('./mediaUpload.js');

            await bladebergMediaUpload({
                filesList: files,
                onFileChange: (attachments) => {
                    if (attachments[0]?.id) onUploaded(attachments[0]);
                },
                onError: (err) => console.error('[BladeBerg] Upload error:', err),
            });
        } finally {
            setUploading(false);
        }
    }, [onUploaded]);

    return (
        <div
            className={`bb-media-upload-zone${dragging ? ' bb-media-upload-zone--drag' : ''}`}
            onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
            onDragLeave={() => setDragging(false)}
            onDrop={(e) => {
                e.preventDefault();
                setDragging(false);
                handleFiles(Array.from(e.dataTransfer.files));
            }}
            onClick={() => inputRef.current?.click()}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => e.key === 'Enter' && inputRef.current?.click()}
        >
            <input
                ref={inputRef}
                type="file"
                multiple
                accept="image/*,video/*,audio/*,application/pdf"
                style={{ display: 'none' }}
                onChange={(e) => handleFiles(Array.from(e.target.files))}
            />
            {uploading
                ? <span>Uploading…</span>
                : <span>Drop files here or click to upload</span>
            }
        </div>
    );
}

/**
 * MediaGrid — main component.
 *
 * @param {Object}   props
 * @param {Function} props.onSelect        Called with the attachment object when item is clicked
 * @param {string[]} [props.allowedTypes]  Filter by MIME type prefixes (e.g. ['image'])
 * @param {boolean}  [props.allowUpload]   Show the upload drop-zone. Default true.
 *                                         Set to false when media.mode = 'select'.
 */
export function MediaGrid({ onSelect, allowedTypes = [], allowUpload = true }) {
    const [items, setItems]           = useState([]);
    const [page, setPage]             = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [search, setSearch]         = useState('');
    const [mediaType, setMediaType]   = useState(
        allowedTypes.length === 1 ? allowedTypes[0] : ''
    );
    const [loading, setLoading]       = useState(false);
    const [error, setError]           = useState(null);
    const [selected, setSelected]     = useState(null);
    const searchTimer = useRef(null);

    const load = useCallback(async (pg = 1, reset = false) => {
        setLoading(true);
        setError(null);
        try {
            const result = await fetchMedia({ page: pg, search, mediaType });
            setItems(prev => reset ? result.items : [...prev, ...result.items]);
            setTotalPages(result.totalPages);
            setPage(pg);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [search, mediaType]);

    // Initial load + reload when filters change
    useEffect(() => { load(1, true); }, [load]);

    const handleSearch = (value) => {
        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => setSearch(value), 350);
    };

    const handleSelect = (item) => {
        setSelected(item.id);
        onSelect(item);
    };

    const handleUploaded = (attachment) => {
        setItems(prev => [attachment, ...prev]);
    };

    // Only show type filter tabs when no type restriction is passed in
    const showTypeFilter = allowedTypes.length !== 1;

    return (
        <div className="bb-media-grid-wrap">
            <div className="bb-media-grid-toolbar">
                <input
                    type="search"
                    className="bb-media-grid-search"
                    placeholder="Search media…"
                    onChange={(e) => handleSearch(e.target.value)}
                />
                {showTypeFilter && (
                    <div className="bb-media-grid-filters" role="tablist">
                        {MIME_FILTERS.map((f) => (
                            <button
                                key={f.value}
                                type="button"
                                role="tab"
                                aria-selected={mediaType === f.value}
                                className={`bb-media-grid-filter${mediaType === f.value ? ' bb-media-grid-filter--active' : ''}`}
                                onClick={() => setMediaType(f.value)}
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {allowUpload && <UploadZone onUploaded={handleUploaded} />}

            {error && <p className="bb-media-grid-error">{error}</p>}

            <div className="bb-media-grid" role="listbox" aria-label="Media library">
                {items.map((item) => (
                    <MediaItem
                        key={item.id}
                        item={item}
                        isSelected={selected === item.id}
                        onSelect={handleSelect}
                    />
                ))}
            </div>

            {!loading && items.length === 0 && (
                <p className="bb-media-grid-empty">
                    {allowUpload
                        ? 'No media found. Drop files above to upload.'
                        : 'No media found in the configured storage directory.'}
                </p>
            )}

            {loading && <p className="bb-media-grid-loading">Loading…</p>}

            {!loading && page < totalPages && (
                <button
                    type="button"
                    className="bb-media-grid-load-more"
                    onClick={() => load(page + 1)}
                >
                    Load more
                </button>
            )}
        </div>
    );
}
