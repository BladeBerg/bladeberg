<?php

declare(strict_types=1);

namespace Bladeberg\Media;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Handles the BladeBerg media API endpoints.
 *
 * Routes (registered in routes/media.php):
 *   GET    /{prefix}/media           → index()
 *   POST   /{prefix}/media           → store()
 *   GET    /{prefix}/media/{id}      → show()
 *   DELETE /{prefix}/media/{id}      → destroy()
 *
 * All responses follow the WordPress REST API attachment shape so that
 * the JS layer (apiFetchMiddleware + mediaUpload) can consume them
 * without transformation.
 */
class BbMediaController extends Controller
{
    public function __construct(private readonly MediaDriverInterface $driver) {}

    /**
     * List media with optional pagination, search, and type filtering.
     *
     * Supports:
     *   ?page=1&per_page=20&search=photo&media_type=image
     *
     * Returns headers compatible with WordPress REST pagination:
     *   X-WP-Total        Total item count
     *   X-WP-TotalPages   Total page count
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->driver->list($request->only('page', 'per_page', 'search', 'media_type'));

        $items = array_map(
            fn (mixed $item) => $item instanceof \Illuminate\Database\Eloquent\Model
                ? $item->toArray()
                : $item,
            $result['data']
        );

        return response()->json($items)
            ->header('X-WP-Total', (string) $result['total'])
            ->header('X-WP-TotalPages', (string) $result['total_pages']);
    }

    /**
     * Upload a new media file.
     *
     * Only available when media.mode is 'upload'. Returns 403 in 'select' mode.
     *
     * Accepts multipart/form-data with:
     *   file      (required) — the binary
     *   alt_text  (optional)
     *   title     (optional)
     *   caption   (optional)
     */
    public function store(Request $request): JsonResponse
    {
        // Upload is not available in browse-only (select) mode.
        if (config('bladeberg.media.mode', 'upload') === 'select') {
            return response()->json(['error' => 'Upload is disabled in select mode.'], 403);
        }

        $allowedMimes = implode(',', config('bladeberg.media.allowed_mime_types', []));
        $maxKb        = (int) config('bladeberg.media.max_file_size_kb', 10240);

        $validator = Validator::make($request->all(), [
            'file'     => ['required', 'file', "mimes:{$allowedMimes}", "max:{$maxKb}"],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'title'    => ['nullable', 'string', 'max:255'],
            'caption'  => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $attachment = $this->driver->upload(
            $request->file('file'),
            $request->only('alt_text', 'title', 'caption')
        );

        return response()->json($attachment, 201);
    }

    /**
     * Return a single attachment by ID.
     */
    public function show(int $id): JsonResponse
    {
        $attachment = $this->driver->find($id);

        return response()->json($attachment);
    }

    /**
     * Delete a media item and its file(s).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->driver->delete($id);

        return response()->json(['deleted' => true, 'id' => $id]);
    }
}
