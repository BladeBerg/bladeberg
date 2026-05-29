<?php

declare(strict_types=1);

namespace Bladeberg\Media;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the filesystem media driver.
 *
 * Stores all metadata about an uploaded file in the `bb_media` table.
 * The actual binary lives on whatever Laravel Storage disk is configured
 * under `bladeberg.media.disk`.
 */
class BbMedia extends Model
{
    protected $table = 'bb_media';

    /** @var list<string> */
    protected $fillable = [
        'filename',
        'disk',
        'path',
        'mime_type',
        'size',
        'alt_text',
        'title',
        'caption',
        'width',
        'height',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'size'   => 'integer',
        'width'  => 'integer',
        'height' => 'integer',
    ];
}
