<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_images', function (Blueprint $table): void {
            $table->id();
            // Nullable so the editor can upload before a recipe row exists.
            // Unclaimed rows are pruned by `php artisan media:prune`.
            $table->foreignId('recipe_id')->nullable()->constrained()->cascadeOnDelete();

            // local  = processed and stored on the media disk
            // remote = hot-linked from the original source
            $table->string('source', 10)->default('local');

            // Populated for local images.
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            // Responsive ladder: [{ width, height, format, path, bytes }, ...]
            $table->json('variants')->nullable();

            // Populated for remote images.
            $table->string('url', 2048)->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            // Tiny inline data URI used as a blur-up placeholder.
            $table->text('placeholder')->nullable();

            $table->string('caption', 300)->nullable();
            $table->string('alt', 300)->nullable();

            $table->boolean('is_hero')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['recipe_id', 'sort_order']);
            $table->index(['recipe_id', 'is_hero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_images');
    }
};
