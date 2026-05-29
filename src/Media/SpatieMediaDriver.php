<?php

declare(strict_types=1);

namespace Bladeberg\Media;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media driver powered by spatie/laravel-medialibrary.
 *
 * All files are attached to the singleton BbMediaPool model (id = 1),
 * providing a global, model-agnostic media library. Image conversion sizes
 * (thumbnail, medium, large) are generated automatically in the queue.
 *
 * Prerequisites:
 *   composer require spatie/laravel-medialibrary
 *   php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
 *   php artisan migrate
 *   php artisan vendor:publish --tag=bladeberg-migrations
 *   php artisan migrate
 */
class SpatieMediaDriver implements MediaDriverInterface
{
    private BbMediaPool $pool;

    /**
     * @throws RuntimeException  When spatie/laravel-medialibrary is not installed.
     */
    public function __construct()
    {
        if (!interface_exists(HasMedia::class)) {
            throw new RuntimeException(
                'The Spatie media driver requires spatie/laravel-medialibrary. ' .
                'Install it with: composer require spatie/laravel-medialibrary'
            );
        }

        $this->pool = BbMediaPool::firstOrCreate(['id' => 1]);
    }

    public function upload(UploadedFile $file, array $meta = []): array
    {
        $spatieMedia = $this->pool
            ->addMedia($file)
            ->usingName($meta['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->usingFileName($file->getClientOriginalName())
            ->withCustomProperties([
                'alt_text' => $meta['alt_text'] ?? '',
                'caption'  => $meta['caption'] ?? '',
            ])
            ->toMediaCollection('default', config('bladeberg.media.disk', 'public'));

        return $this->toAttachment($spatieMedia);
    }

    public function list(array $filters = []): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        $query = Media::where('model_type', BbMediaPool::class)
                      ->where('model_id', 1)
                      ->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('file_name', 'like', $term);
            });
        }

        if (!empty($filters['media_type'])) {
            $query->where('mime_type', 'like', $filters['media_type'] . '/%');
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data'        => array_map(
                fn (Media $m) => $this->toAttachment($m),
                $paginated->items()
            ),
            'total'       => $paginated->total(),
            'total_pages' => $paginated->lastPage(),
        ];
    }

    public function find(int $id): array
    {
        $media = Media::where('model_type', BbMediaPool::class)
                      ->where('model_id', 1)
                      ->findOrFail($id);

        return $this->toAttachment($media);
    }

    public function delete(int $id): void
    {
        $media = Media::where('model_type', BbMediaPool::class)
                      ->where('model_id', 1)
                      ->findOrFail($id);

        $media->delete();
    }

    /**
     * Map a Spatie Media model to the WP-compatible attachment shape.
     *
     * @param  Media  $media
     * @return array<string,mixed>
     */
    private function toAttachment(Media $media): array
    {
        $url   = $media->getUrl();
        $props = $media->custom_properties ?? [];

        $sizes = [];
        foreach (array_keys(config('bladeberg.media.conversions', [])) as $name) {
            if ($media->hasGeneratedConversion($name)) {
                $sizes[$name] = [
                    'source_url' => $media->getUrl($name),
                    'width'      => $media->getCustomProperty("conversion_width_{$name}"),
                    'height'     => $media->getCustomProperty("conversion_height_{$name}"),
                ];
            }
        }

        $dimensions = $media->getCustomProperty('dimensions') ?? [];

        return [
            'id'            => $media->id,
            'source_url'    => $url,
            'url'           => $url,
            'alt_text'      => $props['alt_text'] ?? '',
            'title'         => ['rendered' => $media->name, 'raw' => $media->name],
            'caption'       => [
                'rendered' => $props['caption'] ?? '',
                'raw'      => $props['caption'] ?? '',
            ],
            'mime_type'     => $media->mime_type,
            'media_details' => [
                'width'  => $dimensions['width'] ?? null,
                'height' => $dimensions['height'] ?? null,
                'file'   => $media->file_name,
                'sizes'  => $sizes,
            ],
            'date'          => $media->created_at?->toIso8601String(),
        ];
    }
}
