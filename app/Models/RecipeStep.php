<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $instruction
 * @property int|null $timer_seconds
 */
class RecipeStep extends Model
{
    /** @use HasFactory<RecipeStepFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['instruction', 'timer_seconds', 'recipe_image_id', 'sort_order'];

    protected function casts(): array
    {
        return [
            'timer_seconds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return BelongsTo<RecipeImage, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(RecipeImage::class, 'recipe_image_id');
    }
}
