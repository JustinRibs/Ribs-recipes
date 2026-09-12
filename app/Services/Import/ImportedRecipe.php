<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * A recipe that has been extracted from somewhere but not yet saved.
 *
 * Everything the importers produce lands in this shape, which is also exactly
 * what the editable import preview renders — nothing is ever written to the
 * database until an administrator reviews it and presses Save.
 */
final class ImportedRecipe
{
    /**
     * @param  list<array<string, mixed>>  $ingredients
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $tags
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $title = '',
        public ?string $description = null,
        public ?string $notes = null,
        public ?int $prepMinutes = null,
        public ?int $cookMinutes = null,
        public ?int $totalMinutes = null,
        public ?int $servings = null,
        public ?string $servingsLabel = null,
        public ?int $calories = null,
        public ?string $sourceUrl = null,
        public ?string $sourceName = null,
        public ?string $heroImageUrl = null,
        public array $ingredients = [],
        public array $steps = [],
        public array $tags = [],
        public array $warnings = [],
        public string $extractedVia = 'unknown',
    ) {}

    public function isUsable(): bool
    {
        return $this->title !== '' && ($this->ingredients !== [] || $this->steps !== []);
    }

    /**
     * The shape the React import preview binds to — identical to the recipe
     * editor's form state, so the preview *is* the editor.
     *
     * @return array<string, mixed>
     */
    public function toFormState(): array
    {
        // Only override the total when it genuinely differs from prep + cook,
        // so the editor's "calculated" hint stays accurate.
        $sum = ($this->prepMinutes ?? 0) + ($this->cookMinutes ?? 0);
        $override = ($this->totalMinutes !== null && $this->totalMinutes !== $sum)
            ? $this->totalMinutes
            : null;

        return [
            'title' => $this->title,
            // Left blank so the slug is generated from the (possibly edited)
            // title when the recipe is finally saved.
            'slug' => '',
            'description' => $this->description ?? '',
            'notes' => $this->notes ?? '',
            'category_id' => null,
            'tags' => $this->tags,
            'prep_minutes' => $this->prepMinutes,
            'cook_minutes' => $this->cookMinutes,
            'total_minutes_override' => $override,
            'servings' => $this->servings,
            'servings_label' => $this->servingsLabel ?? '',
            'calories' => $this->calories,
            'source_url' => $this->sourceUrl ?? '',
            'source_name' => $this->sourceName ?? '',
            'is_favorite' => false,
            'status' => 'draft',
            'ingredients' => $this->ingredients,
            'steps' => $this->steps,
            'images' => [],
        ];
    }
}
