<?php

declare(strict_types=1);

namespace Bladeberg\Media;

use Illuminate\Http\UploadedFile;

/**
 * Contract every BladeBerg media driver must satisfy.
 *
 * All methods return plain arrays shaped to match the WordPress REST API
 * attachment format so that the JS layer can consume them unchanged.
 */
interface MediaDriverInterface
{
    /**
     * Persist an uploaded file and return a WP-compatible attachment array.
     *
     * @param  UploadedFile         $file
     * @param  array<string,mixed>  $meta  Optional alt_text / title / caption
     * @return array<string,mixed>
     */
    public function upload(UploadedFile $file, array $meta = []): array;

    /**
     * Return a paginated list of attachments.
     *
     * @param  array<string,mixed>  $filters  Supports: page, per_page, search, media_type
     * @return array{data: array<int,array<string,mixed>>, total: int, total_pages: int}
     */
    public function list(array $filters = []): array;

    /**
     * Return a single attachment by its ID.
     *
     * @param  int  $id
     * @return array<string,mixed>
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(int $id): array;

    /**
     * Permanently delete a media item and its associated file(s).
     *
     * @param  int  $id
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete(int $id): void;
}
