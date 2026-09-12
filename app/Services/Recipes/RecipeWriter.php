<?php

declare(strict_types=1);

namespace App\Services\Recipes;

use App\Enums\RecipeStatus;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Services\Images\ImageStore;
use App\Support\Quantity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single write path for recipes.
 *
 * Manual creation, the URL importer and the CSV importer all funnel through
 * here so slugs, derived totals, ingredient normalisation, image ownership and
 * the search index stay consistent no matter where a recipe came from.
 */
final class RecipeWriter
{
    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly TagResolver $tags,
        private readonly RecipeSearchIndex $searchIndex,
        private readonly ImageStore $images,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): Recipe
    {
        return DB::transaction(function () use ($data, $userId): Recipe {
            $recipe = new Recipe;
            $recipe->user_id = $userId;
            $recipe->slug = $this->slugs->generate(
                Recipe::class,
                Arr::get($data, 'slug') ?: (string) Arr::get($data, 'title', '')
            );

            $this->fill($recipe, $data);
            $recipe->save();

            $this->syncRelations($recipe, $data);

            return $recipe;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Recipe $recipe, array $data): Recipe
    {
        return DB::transaction(function () use ($recipe, $data): Recipe {
            if (array_key_exists('slug', $data) && filled($data['slug']) && $data['slug'] !== $recipe->slug) {
                $recipe->slug = $this->slugs->generate(Recipe::class, (string) $data['slug'], $recipe->id);
            }

            $this->fill($recipe, $data);
            $recipe->save();

            $this->syncRelations($recipe, $data);

            return $recipe;
        });
    }

    /**
     * Copy a recipe, including its ingredients, steps, tags and image records.
     * Local image files are shared rather than duplicated; the copy owns its
     * own rows so deleting one recipe cannot orphan the other's variants.
     */
    public function duplicate(Recipe $recipe, ?int $userId = null): Recipe
    {
        return DB::transaction(function () use ($recipe, $userId): Recipe {
            $recipe->loadMissing(['ingredients', 'steps', 'images', 'tags']);

            $copy = $recipe->replicate(['slug', 'published_at', 'deleted_at']);
            $copy->title = Str::limit($recipe->title.' (copy)', 255, '');
            $copy->slug = $this->slugs->generate(Recipe::class, $copy->title);
            $copy->status = RecipeStatus::Draft;
            $copy->published_at = null;
            $copy->is_favorite = false;
            $copy->user_id = $userId ?? $recipe->user_id;
            $copy->save();

            $copy->tags()->sync($recipe->tags->pluck('id')->all());

            foreach ($recipe->ingredients as $ingredient) {
                $copy->ingredients()->create($ingredient->only(
                    ['quantity', 'quantity_display', 'unit', 'name', 'note', 'sort_order']
                ));
            }

            $imageMap = [];

            foreach ($recipe->images as $image) {
                $clone = $image->replicate(['recipe_id']);
                $clone->recipe_id = $copy->id;
                // Both rows point at the same files; ImageStore::deleteFiles()
                // reference-counts paths, so deleting one copy leaves the
                // other's photos intact.
                $clone->save();
                $imageMap[$image->id] = $clone->id;
            }

            foreach ($recipe->steps as $step) {
                $copy->steps()->create([
                    'instruction' => $step->instruction,
                    'timer_seconds' => $step->timer_seconds,
                    'recipe_image_id' => $imageMap[$step->recipe_image_id] ?? null,
                    'sort_order' => $step->sort_order,
                ]);
            }

            $this->searchIndex->sync($copy->fresh(['ingredients', 'category', 'tags']));

            return $copy;
        });
    }

    public function delete(Recipe $recipe): void
    {
        DB::transaction(function () use ($recipe): void {
            $this->searchIndex->forget($recipe->id);
            // Soft delete: files and rows survive so a mistake is recoverable.
            $recipe->delete();
        });
    }

    public function forceDelete(Recipe $recipe): void
    {
        DB::transaction(function () use ($recipe): void {
            $this->searchIndex->forget($recipe->id);

            foreach ($recipe->images()->withoutGlobalScopes()->get() as $image) {
                $this->images->deleteFiles($image);
            }

            $recipe->forceDelete();
        });
    }

    public function restore(Recipe $recipe): void
    {
        DB::transaction(function () use ($recipe): void {
            $recipe->restore();
            $this->searchIndex->sync($recipe->fresh(['ingredients', 'category', 'tags']));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Recipe $recipe, array $data): void
    {
        foreach ([
            'title', 'description', 'notes', 'category_id', 'prep_minutes', 'cook_minutes',
            'total_minutes_override', 'servings', 'servings_label', 'calories',
            'source_url', 'source_name', 'is_favorite',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $recipe->{$field} = $this->blankToNull($data[$field]);
            }
        }

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $status = $data['status'] instanceof RecipeStatus
                ? $data['status']
                : RecipeStatus::from((string) $data['status']);

            $recipe->status = $status;

            // Stamp the first publication date; keep it stable afterwards.
            if ($status === RecipeStatus::Published && $recipe->published_at === null) {
                $recipe->published_at = now();
            }
        }

        if (array_key_exists('published_at', $data) && filled($data['published_at'])) {
            $recipe->published_at = $data['published_at'];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Recipe $recipe, array $data): void
    {
        if (array_key_exists('tags', $data)) {
            $recipe->tags()->sync($this->resolveTagIds($data['tags']));
        }

        if (array_key_exists('ingredients', $data)) {
            $this->syncIngredients($recipe, (array) $data['ingredients']);
        }

        if (array_key_exists('images', $data)) {
            $this->syncImages($recipe, (array) $data['images']);
        }

        // Steps may reference images, so they are written last.
        if (array_key_exists('steps', $data)) {
            $this->syncSteps($recipe, (array) $data['steps']);
        }

        $this->searchIndex->sync($recipe->fresh(['ingredients', 'category', 'tags']));
    }

    /**
     * @param  mixed  $tags  A list of tag ids, tag names, or a mix of both.
     * @return list<int>
     */
    private function resolveTagIds(mixed $tags): array
    {
        $names = [];
        $ids = [];

        foreach ((array) $tags as $tag) {
            if (is_int($tag) || (is_string($tag) && ctype_digit($tag))) {
                $ids[] = (int) $tag;
            } elseif (is_string($tag)) {
                $names[] = $tag;
            }
        }

        return array_values(array_unique([...$ids, ...$this->tags->resolveIds($names)]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncIngredients(Recipe $recipe, array $rows): void
    {
        $recipe->ingredients()->delete();

        $order = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $display = isset($row['quantity_display']) ? trim((string) $row['quantity_display']) : null;
            $quantity = array_key_exists('quantity', $row) && $row['quantity'] !== null && $row['quantity'] !== ''
                ? (float) $row['quantity']
                : Quantity::parse($display);

            $recipe->ingredients()->create([
                'quantity' => $quantity,
                'quantity_display' => Quantity::displayFor($quantity, $display),
                'unit' => $this->blankToNull(Str::limit((string) ($row['unit'] ?? ''), 40, '')),
                'name' => Str::limit($name, 200, ''),
                'note' => $this->blankToNull(Str::limit((string) ($row['note'] ?? ''), 200, '')),
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncSteps(Recipe $recipe, array $rows): void
    {
        $recipe->steps()->delete();

        $ownedImageIds = $recipe->images()->pluck('id')->all();
        $order = 0;

        foreach ($rows as $row) {
            $instruction = trim((string) ($row['instruction'] ?? ''));

            if ($instruction === '') {
                continue;
            }

            $imageId = isset($row['recipe_image_id']) ? (int) $row['recipe_image_id'] : null;

            $recipe->steps()->create([
                'instruction' => $instruction,
                'timer_seconds' => isset($row['timer_seconds']) && $row['timer_seconds'] !== null && $row['timer_seconds'] !== ''
                    ? max(0, (int) $row['timer_seconds']) ?: null
                    : null,
                'recipe_image_id' => in_array($imageId, $ownedImageIds, true) ? $imageId : null,
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * Claim the images the editor selected, in order, and discard the rest.
     *
     * Each entry is `{ id, caption?, alt?, is_hero? }`. Ids that belong to
     * another recipe are ignored, so a crafted payload cannot steal media.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncImages(Recipe $recipe, array $rows): void
    {
        $keptIds = [];
        $order = 0;
        $heroAssigned = false;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            /** @var RecipeImage|null $image */
            $image = RecipeImage::query()
                ->where('id', $id)
                ->where(function ($q) use ($recipe): void {
                    $q->whereNull('recipe_id')->orWhere('recipe_id', $recipe->id);
                })
                ->first();

            if ($image === null) {
                continue;
            }

            $isHero = ! $heroAssigned && (bool) ($row['is_hero'] ?? false);
            $heroAssigned = $heroAssigned || $isHero;

            $image->fill([
                'caption' => $this->blankToNull(Str::limit((string) ($row['caption'] ?? ''), 300, '')),
                'alt' => $this->blankToNull(Str::limit((string) ($row['alt'] ?? ''), 300, '')),
                'is_hero' => $isHero,
                'sort_order' => $order++,
            ]);
            $image->recipe_id = $recipe->id;
            $image->save();

            $keptIds[] = $image->id;
        }

        // Nothing was flagged as hero — promote the first image so recipe
        // cards and Open Graph tags always have something to show.
        if (! $heroAssigned && $keptIds !== []) {
            RecipeImage::query()->whereKey($keptIds[0])->update(['is_hero' => true]);
        }

        $removed = $recipe->images()->whereNotIn('id', $keptIds ?: [0])->get();

        foreach ($removed as $image) {
            $this->images->deleteFiles($image);
            $image->delete();
        }
    }

    private function blankToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
