<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->unsignedSmallInteger('cook_minutes')->nullable();
            // Only set when the author overrides prep + cook (resting time,
            // overnight soaks, and so on). Null means "compute it".
            $table->unsignedSmallInteger('total_minutes_override')->nullable();

            $table->unsignedSmallInteger('servings')->nullable();
            // Free-text noun for the yield, e.g. "servings", "cookies", "jars".
            $table->string('servings_label', 40)->nullable();
            $table->unsignedSmallInteger('calories')->nullable();

            $table->string('source_url', 2048)->nullable();
            $table->string('source_name')->nullable();

            $table->boolean('is_favorite')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Public listings are always "published, newest first", optionally
            // narrowed by category or the featured flag.
            $table->index(['status', 'published_at']);
            $table->index(['status', 'is_favorite']);
            $table->index(['category_id', 'status']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
