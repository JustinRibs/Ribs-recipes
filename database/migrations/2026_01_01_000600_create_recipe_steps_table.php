<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();

            $table->text('instruction');
            // Explicit, author-entered timer. Detected durations are derived at
            // render time and are deliberately not persisted here.
            $table->unsignedInteger('timer_seconds')->nullable();

            $table->foreignId('recipe_image_id')->nullable()
                ->constrained('recipe_images')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['recipe_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_steps');
    }
};
