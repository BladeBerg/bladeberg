<?php

declare(strict_types=1);

namespace Bladeberg\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Media driver backed entirely by Laravel Storage — no database table required.
 *
 * Files are stored in the configured disk / directory and listed by scanning
 * that directory at request time. This means BladeBerg re-uses whatever
 * storage your app already has set up (local, public, S3, R2, MinIO, etc.)
 * without any additional infrastructure.
 *
 * How IDs work
 * ─────────────
 * There is no primary-key sequence. Each file's ID is the absolute value of
 * crc32() of its path relative to the disk root. This gives a stable,
 * deterministic integer that survives server restarts. The probability of
 * collision is negligible for directories with fewer than ~100 000 files.
 *
 * Limitations vs. the Spatie driver
 * ───────────────────────────────────
 *  - No automatic thumbnail / conversion generation.
 *  - media_details.sizes is always empty.
 *  - Sorting is by last-modified time (filesystem metadata).
 *  - Pagination and search are done in PHP after a full directory listing,
 *    which is inefficient for very large libraries (thousands of files).
 *    For large libraries consider switching to the Spatie driver.
 */
class FilesystemMediaDriver implements MediaDriverInterface
{
    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function disk(): Filesystem
    {
        return Storage::disk(config('bladeberg.media.disk', 'public'));
    }

    private function dir(): string
    {
        return rtrim((string) config('bladeberg.media.directory', 'bladeberg'), '/');
    }

    /**
     * Stable integer ID derived from the file's relative path on the disk.
     */
    private function pathToId(string $path): int
    {
        return abs(crc32($path));
    }

    // -----------------------------------------------------------------------
    // MediaDriverInterface
    // -----------------------------------------------------------------------

    public function upload(UploadedFile $file, array $meta = []): array
    {
        $dir  = $this->dir();
        $ext  = $file->getClientOriginalExtension();
        $name = Str::uuid() . ($ext ? ".{$ext}" : '');

        $path = $this->disk()->putFileAs($dir, $file, $name);

        if ($path === false) {
            throw new \RuntimeException('[BladeBerg] Failed to store uploaded file.');
        }

        return $this->pathToAttachment($path, $meta);
    }

    public function list(array $filters = []): array
    {
        $disk = $this->disk();
        $dir  = $this->dir();

        if (!$disk->directoryExists($dir)) {
            return ['data' => [], 'total' => 0, 'total_pages' => 1];
        }

        $files = $disk->files($dir);

        // ── Filter by MIME type prefix ───────────────────────────────────
        if (!empty($filters['media_type'])) {
            $prefix = strtolower($filters['media_type']) . '/';
            $files  = array_values(array_filter(
                $files,
                fn (string $p) => str_starts_with(strtolower((string) $disk->mimeType($p)), $prefix)
            ));
        }

        // ── Filter by search term (filename only) ────────────────────────
        if (!empty($filters['search'])) {
            $term  = strtolower($filters['search']);
            $files = array_values(array_filter(
                $files,
                fn (string $p) => str_contains(strtolower(basename($p)), $term)
            ));
        }

        // ── Sort newest first ────────────────────────────────────────────
        usort($files, function (string $a, string $b) use ($disk): int {
            $ta = $disk->lastModified($a) ?? 0;
            $tb = $disk->lastModified($b) ?? 0;
            return $tb <=> $ta;
        });

        // ── Paginate ─────────────────────────────────────────────────────
        $total   = count($files);
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $slice   = array_slice($files, ($page - 1) * $perPage, $perPage);

        return [
            'data'        => array_map(fn (string $p) => $this->pathToAttachment($p), $slice),
            'total'       => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function find(int $id): array
    {
        $disk  = $this->disk();
        $dir   = $this->dir();
        $files = $disk->files($dir);

        foreach ($files as $path) {
            if ($this->pathToId($path) === $id) {
                return $this->pathToAttachment($path);
            }
        }

        throw new \RuntimeException("[BladeBerg] Media item not found: {$id}");
    }

    public function delete(int $id): void
    {
        $disk  = $this->disk();
        $dir   = $this->dir();
        $files = $disk->files($dir);

        foreach ($files as $path) {
            if ($this->pathToId($path) === $id) {
                $disk->delete($path);
                return;
            }
        }

        throw new \RuntimeException("[BladeBerg] Media item not found: {$id}");
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a WP-compatible attachment array from a disk path.
     *
     * @param  string               $path  Path relative to disk root
     * @param  array<string,mixed>  $meta  Optional title / alt_text / caption overrides
     * @return array<string,mixed>
     */
    private function pathToAttachment(string $path, array $meta = []): array
    {
        $disk     = $this->disk();
        $url      = $disk->url($path);
        $mime     = (string) ($disk->mimeType($path) ?: 'application/octet-stream');
        $size     = $disk->size($path) ?: 0;
        $filename = basename($path);
        $title    = (string) ($meta['title'] ?? pathinfo($filename, PATHINFO_FILENAME));

        [$width, $height] = $this->imageDimensions($disk, $path, $mime);

        return [
            'id'           => $this->pathToId($path),
            'source_url'   => $url,
            'url'          => $url,
            'alt_text'     => (string) ($meta['alt_text'] ?? ''),
            'title'        => ['rendered' => $title, 'raw' => $title],
            'caption'      => ['rendered' => (string) ($meta['caption'] ?? ''), 'raw' => (string) ($meta['caption'] ?? '')],
            'mime_type'    => $mime,
            'media_type'   => str_starts_with($mime, 'image/') ? 'image' : 'file',
            'media_details' => [
                'width'  => $width,
                'height' => $height,
                'file'   => $path,
                'sizes'  => [],
            ],
            'filesize'     => $size,
            'filename'     => $filename,
            'path'         => $path,
            'date'         => $this->fileDate($disk, $path),
        ];
    }

    /**
     * Return [width, height] for image files; [null, null] for everything else.
     *
     * @return array{int|null, int|null}
     */
    private function imageDimensions(Filesystem $disk, string $path, string $mime): array
    {
        if (!str_starts_with($mime, 'image/') || str_contains($mime, 'svg')) {
            return [null, null];
        }

        try {
            $contents = $disk->get($path);
            if ($contents === null) {
                return [null, null];
            }
            $info = @getimagesizefromstring($contents);
            return $info ? [(int) $info[0], (int) $info[1]] : [null, null];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    /**
     * Return an ISO-8601 date string for the file's last-modified timestamp.
     */
    private function fileDate(Filesystem $disk, string $path): string
    {
        try {
            $ts = $disk->lastModified($path);
            return $ts ? (new \DateTime())->setTimestamp($ts)->format('c') : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
