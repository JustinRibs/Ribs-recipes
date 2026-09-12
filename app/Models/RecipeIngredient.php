<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeIngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property float|null $quantity
 * @property string|null $quantity_display
 * @property string|null $unit
 * @property string $name
 * @property string|null $note
 */
class RecipeIngredient extends Model
{
    /** @use HasFactory<RecipeIngredientFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['quantity', 'quantity_display', 'unit', 'name', 'note', 'sort_order'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Ingredient names are indexed, so "pomegranate molasses" finds
        // the recipe that uses it even when the title says nothing about it.
        static::saved(function (self $row): void {
            $row->recipe?->touchSearchIndex();
        });

        static::deleted(function (self $row): void {
            $row->recipe?->touchSearchIndex();
        });
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
