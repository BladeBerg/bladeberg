<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the BladeBerg filesystem media driver.
 *
 * Publish and run this migration:
 *   php artisan vendor:publish --tag=bladeberg-migrations
 *   php artisan migrate
 *
 * Only required when using driver = 'filesystem' in config/bladeberg.php.
 * When using the Spatie driver, publish and run Spatie's own migration instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_media', function (Blueprint $table): void {
            $table->id();
            $table->string('filename');
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('title')->default('');
            $table->string('alt_text')->default('');
            $table->text('caption')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_media');
    }
};
