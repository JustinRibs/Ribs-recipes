<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const NAV_CACHE_KEY = 'ribs.nav.categories';

    protected $fillable = ['name', 'slug', 'description', 'icon', 'color', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // The header's category list is cached; renaming or reordering a
        // category should show up immediately, not in ten minutes.
        // Block bodies: a model listener returning false halts the rest of
        // the listeners, and Cache::forget() returns false on a cold key.
        static::saved(function (): void {
            Cache::forget(self::NAV_CACHE_KEY);
        });

        static::deleted(function (): void {
            Cache::forget(self::NAV_CACHE_KEY);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Recipe, $this> */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
