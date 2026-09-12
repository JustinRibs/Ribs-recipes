<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();

            // Normalised numeric value, used for scaling. Null when the amount
            // is not a number ("to taste", "a pinch").
            $table->decimal('quantity', 10, 4)->nullable();
            // Exactly what the author typed: "1 1/2", "2-3", "½". Preserved so
            // an un-scaled recipe always reads the way it was written.
            $table->string('quantity_display', 60)->nullable();

            $table->string('unit', 40)->nullable();
            $table->string('name', 200);
            $table->string('note', 200)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['recipe_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
