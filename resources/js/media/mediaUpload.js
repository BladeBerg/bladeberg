/**
 * BladeBerg media upload function.
 *
 * Passed to the isolated-block-editor as `settings.editor.mediaUpload`.
 * The editor calls this whenever a user drops files onto a block placeholder,
 * clicks an upload button, or pastes a file into the canvas.
 *
 * Signature matches @wordpress/media-utils uploadMedia:
 *   { filesList, onFileChange, onSuccess, onError, additionalData, signal }
 */

/**
 * Map a raw server attachment object to the shape that block-editor
 * components expect (same as @wordpress/media-utils transformAttachment).
 *
 * @param {Object} attachment
 * @returns {Object}
 */
function transformAttachment(attachment) {
    return {
        ...attachment,
        alt:     attachment.alt_text   ?? '',
        caption: attachment.caption?.raw ?? '',
        title:   attachment.title?.raw   ?? attachment.title ?? '',
        url:     attachment.source_url  ?? attachment.url ?? '',
    };
}

/**
 * Upload a single file to /bladeberg/media and return the shaped attachment.
 *
 * @param {File}   file
 * @param {Object} additionalData  Extra fields (alt_text, title, caption, etc.)
 * @param {AbortSignal|undefined} signal
 * @returns {Promise<Object>}
 */
async function uploadSingleFile(file, additionalData = {}, signal) {
    const cfg      = window.BladebergConfig ?? {};
    const apiUrl   = cfg.mediaApiUrl;
    const csrfToken = cfg.csrfToken ?? document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (!apiUrl) {
        throw new Error('[BladeBerg] mediaApiUrl is not configured. Set media.enabled = true in config/bladeberg.php.');
    }

    const formData = new FormData();
    formData.append('file', file, file.name || 'upload');

    const allowed = ['alt_text', 'title', 'caption'];
    for (const key of allowed) {
        if (additionalData[key] !== undefined && additionalData[key] !== null) {
            formData.append(key, additionalData[key]);
        }
    }

    const headers = {};
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

    const response = await fetch(apiUrl, {
        method: 'POST',
        headers,
        body: formData,
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const message = body?.message ?? body?.errors?.file?.[0] ?? `HTTP ${response.status}`;
        throw new Error(`[BladeBerg] Upload failed: ${message}`);
    }

    return transformAttachment(await response.json());
}

/**
 * BladeBerg implementation of the Gutenberg mediaUpload API.
 *
 * @param {Object}   options
 * @param {FileList|File[]} options.filesList
 * @param {Function} options.onFileChange   Called after each file with interim/final attachment
 * @param {Function} [options.onSuccess]    Called after ALL files succeed
 * @param {Function} [options.onError]      Called on individual file errors
 * @param {Object}   [options.additionalData]
 * @param {AbortSignal} [options.signal]
 */
export async function bladebergMediaUpload({
    filesList,
    onFileChange,
    onSuccess,
    onError,
    additionalData = {},
    signal,
}) {
    const files      = Array.from(filesList);
    const successful = [];

    for (const file of files) {
        // Emit a blob-URL preview immediately so the block shows something
        // while the real upload is in-flight.
        const blobUrl    = URL.createObjectURL(file);
        const placeholder = { url: blobUrl, media_type: file.type };
        onFileChange([placeholder]);

        try {
            const attachment = await uploadSingleFile(file, additionalData, signal);
            URL.revokeObjectURL(blobUrl);

            onFileChange([attachment]);
            successful.push(attachment);
        } catch (err) {
            URL.revokeObjectURL(blobUrl);

            if (typeof onError === 'function') {
                onError({ code: 'upload_error', message: err.message, file });
            } else {
                console.error('[BladeBerg] Media upload error:', err);
            }
        }
    }

    if (successful.length > 0 && typeof onSuccess === 'function') {
        onSuccess(successful);
    }
}
