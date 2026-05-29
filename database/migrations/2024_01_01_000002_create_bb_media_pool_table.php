<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the BladeBerg Spatie media driver pool model.
 *
 * This table holds a single row (id = 1) that acts as the owner model
 * for all Spatie media attachments. The actual file metadata lives in
 * Spatie's `media` table.
 *
 * Publish and run this migration alongside Spatie's own migration:
 *   php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
 *   php artisan vendor:publish --tag=bladeberg-migrations
 *   php artisan migrate
 *
 * Only required when using driver = 'spatie' in config/bladeberg.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_media_pool', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_media_pool');
    }
};
