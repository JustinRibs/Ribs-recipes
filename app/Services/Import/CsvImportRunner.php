<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\RecipeStatus;
use App\Models\Category;
use App\Models\Recipe;
use App\Services\Images\ImageStore;
use App\Services\Recipes\RecipeWriter;
use App\Services\Recipes\SlugGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns reviewed CSV rows into recipes.
 *
 * Each row is written in its own transaction (RecipeWriter opens one), so a
 * single bad row fails alone and leaves the rest of the batch intact — the
 * collection is never left half-corrupted by one malformed recipe.
 */
final class CsvImportRunner
{
    public function __construct(
        private readonly RecipeWriter $writer,
        private readonly ImageStore $images,
        private readonly SlugGenerator $slugs,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  Parsed rows from CsvRecipeParser.
     * @param  list<int>  $selected  Indices the administrator ticked.
     * @param  array<string, mixed>  $options
     * @return array{imported: list<array<string, mixed>>, failed: list<array<string, mixed>>}
     */
    public function run(array $rows, array $selected, array $options, ?int $userId): array
    {
        $imported = [];
        $failed = [];

        $status = $options['status'] ?? RecipeStatus::Draft->value;
        $defaultCategoryId = $options['category_id'] ?? null;
        $downloadImages = (bool) ($options['download_images'] ?? false);
        $extraTags = (array) ($options['extra_tags'] ?? []);

        foreach ($rows as $row) {
            if (! in_array((int) $row['index'], $selected, true) || $row['valid'] !== true) {
                continue;
            }

            try {
                $imported[] = $this->importRow($row, $status, $defaultCategoryId, $extraTags, $downloadImages, $userId);
            } catch (\Throwable $e) {
                Log::channel('import')->error('CSV row failed', [
                    'line' => $row['line'] ?? null,
                    'title' => $row['title'] ?? null,
                    'reason' => $e->getMessage(),
                ]);

                $failed[] = [
                    'line' => $row['line'] ?? null,
                    'title' => $row['title'] ?? '',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return ['imported' => $imported, 'failed' => $failed];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $extraTags
     * @return array<string, mixed>
     */
    private function importRow(
        array $row,
        string $status,
        ?int $defaultCategoryId,
        array $extraTags,
        bool $downloadImages,
        ?int $userId,
    ): array {
        $data = $row['recipe'];

        // A recipe with nothing to cook is always a draft, whatever was asked.
        $publishable = $data['ingredients'] !== [] && $data['steps'] !== [];

        $data['status'] = $publishable ? $status : RecipeStatus::Draft->value;
        $data['category_id'] = $this->resolveCategoryId($row['category'] ?? null) ?? $defaultCategoryId;
        $data['tags'] = array_values(array_unique([...(array) ($row['tags'] ?? []), ...$extraTags]));

        $recipe = $this->writer->create($data, $userId);

        $imageNote = $this->attachHeroImage($recipe, $row['heroImageUrl'] ?? null, $downloadImages);

        return [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'status' => $recipe->status->value,
            'url' => route('admin.recipes.edit', $recipe->id),
            'note' => $imageNote,
        ];
    }

    private function attachHeroImage(Recipe $recipe, ?string $url, bool $download): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        try {
            $image = $download
                ? $this->images->downloadRemote($url, $recipe->title)
                : $this->images->linkRemote($url, $recipe->title);
        } catch (\Throwable $e) {
            Log::channel('import')->warning('CSV hero image skipped', [
                'recipe' => $recipe->id,
                'reason' => $e->getMessage(),
            ]);

            return 'The hero image could not be fetched.';
        }

        $image->forceFill([
            'recipe_id' => $recipe->id,
            'is_hero' => true,
            'sort_order' => 0,
        ])->save();

        return null;
    }

    private function resolveCategoryId(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $slug = Str::of($name)->ascii()->slug('-')->toString();

        $existing = Category::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            return $existing->id;
        }

        return Category::create([
            'name' => Str::limit($name, 60, ''),
            'slug' => $this->slugs->generate(Category::class, $name),
            'sort_order' => ((int) Category::query()->max('sort_order')) + 10,
        ])->id;
    }
}
