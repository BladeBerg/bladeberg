<?php

declare(strict_types=1);

namespace Bladeberg\Media;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Singleton pool model used by the Spatie media driver.
 *
 * All BladeBerg media is attached to the single row with id = 1,
 * providing a global, model-agnostic media library (similar to the
 * WordPress media library which lives independently of any post).
 *
 * This class is only loaded when `spatie/laravel-medialibrary` is installed.
 * The service provider guards against loading it without the package.
 */
class BbMediaPool extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'bb_media_pool';

    /** @var list<string> */
    protected $fillable = ['id'];

    /**
     * Register image size conversions.
     *
     * Sizes are driven by config('bladeberg.media.conversions') so developers
     * can customise them without touching package code.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $conversions = config('bladeberg.media.conversions', [
            'thumbnail' => ['width' => 300,  'height' => 300],
            'medium'    => ['width' => 768,  'height' => null],
            'large'     => ['width' => 1200, 'height' => null],
        ]);

        foreach ($conversions as $name => $dimensions) {
            $conversion = $this->addMediaConversion($name)->queued();

            if (!empty($dimensions['width'])) {
                $conversion->width((int) $dimensions['width']);
            }

            if (!empty($dimensions['height'])) {
                $conversion->height((int) $dimensions['height']);
            }
        }
    }
}
