<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecipeStatus;
use App\Services\Recipes\RecipeSearchIndex;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property RecipeStatus $status
 * @property bool $is_favorite
 * @property int|null $prep_minutes
 * @property int|null $cook_minutes
 * @property int|null $total_minutes_override
 */
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'description', 'notes',
        'prep_minutes', 'cook_minutes', 'total_minutes_override',
        'servings', 'servings_label', 'calories',
        'source_url', 'source_name', 'is_favorite', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecipeStatus::class,
            'is_favorite' => 'boolean',
            'published_at' => 'datetime',
            'prep_minutes' => 'integer',
            'cook_minutes' => 'integer',
            'total_minutes_override' => 'integer',
            'servings' => 'integer',
            'calories' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Model event listeners here deliberately use block bodies returning void.
     * An Eloquent listener that returns false stops the event propagating to
     * the listeners after it — and Cache::forget() returns false whenever the
     * key was not set, which would silently stop the search index updating
     * every time the navigation cache happened to be cold.
     */
    protected static function booted(): void
    {
        // Publishing the first recipe in a category makes that category
        // appear in the header, so the cached list has to be dropped.
        static::saved(function (Recipe $recipe): void {
            Cache::forget(Category::NAV_CACHE_KEY);

            // Keeping the full-text index in step with the row is the model's
            // business, not one service's: a recipe written from a seeder, a
            // console command or tinker stays searchable too.
            $recipe->touchSearchIndex();
        });

        static::restored(function (Recipe $recipe): void {
            $recipe->touchSearchIndex();
        });

        static::deleted(function (Recipe $recipe): void {
            Cache::forget(Category::NAV_CACHE_KEY);
            app(RecipeSearchIndex::class)->forget($recipe->id);
        });
    }

    /** Rewrite this recipe's row in the full-text index. */
    public function touchSearchIndex(): void
    {
        if ($this->exists && $this->deleted_at === null) {
            app(RecipeSearchIndex::class)->sync($this);
        }
    }

    // -------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<RecipeStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<RecipeImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(RecipeImage::class)
            ->orderByDesc('is_hero')->orderBy('sort_order')->orderBy('id');
    }

    /** Additional (non-hero) images, in gallery order. @return HasMany<RecipeImage, $this> */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(RecipeImage::class)
            ->where('is_hero', false)
            ->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasOne<RecipeImage, $this> */
    public function heroImage(): HasOne
    {
        return $this->hasOne(RecipeImage::class)->where('is_hero', true);
    }

    // ----------------------------------------------------------------- scopes

    /** @param  Builder<Recipe>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', RecipeStatus::Published->value)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /** @param  Builder<Recipe>  $query */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', RecipeStatus::Draft->value);
    }

    /** @param  Builder<Recipe>  $query */
    public function scopeFavorite(Builder $query): void
    {
        $query->where('is_favorite', true);
    }

    // ------------------------------------------------------------- attributes

    public function isPublished(): bool
    {
        return $this->status === RecipeStatus::Published
            && (is_null($this->published_at) || $this->published_at->isPast());
    }

    /**
     * Total time in minutes: the author's override when present, otherwise the
     * sum of prep and cook. Null when neither is known.
     */
    public function totalMinutes(): ?int
    {
        if ($this->total_minutes_override !== null) {
            return $this->total_minutes_override;
        }

        $sum = ($this->prep_minutes ?? 0) + ($this->cook_minutes ?? 0);

        return $sum > 0 ? $sum : null;
    }
}
