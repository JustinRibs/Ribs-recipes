<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RecipeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for the recipe editor.
 *
 * Blank rows are stripped before validation rather than rejected, because the
 * editor always keeps one empty ingredient and step row at the bottom for
 * quick entry — asking someone to delete it before saving would be hostile.
 */
class RecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reaching this request already required a verified Cloudflare Access
        // identity; per-recipe ownership is enforced by RecipePolicy.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],

            'tags' => ['array', 'max:20'],
            'tags.*' => ['string', 'max:60'],

            'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'cook_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'total_minutes_override' => ['nullable', 'integer', 'min:0', 'max:20160'],
            'servings' => ['nullable', 'integer', 'min:1', 'max:999'],
            'servings_label' => ['nullable', 'string', 'max:40'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:20000'],

            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:255'],

            'is_favorite' => ['boolean'],
            'status' => ['required', Rule::enum(RecipeStatus::class)],

            'ingredients' => ['array', 'max:200'],
            'ingredients.*.quantity_display' => ['nullable', 'string', 'max:60'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:40'],
            'ingredients.*.name' => ['required', 'string', 'max:200'],
            'ingredients.*.note' => ['nullable', 'string', 'max:200'],

            'steps' => ['array', 'max:100'],
            'steps.*.instruction' => ['required', 'string', 'max:5000'],
            'steps.*.timer_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],

            'images' => ['array', 'max:24'],
            'images.*.id' => ['required', 'integer', 'min:1'],
            'images.*.caption' => ['nullable', 'string', 'max:300'],
            'images.*.alt' => ['nullable', 'string', 'max:300'],
            'images.*.is_hero' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ingredients.*.name.required' => 'Give this ingredient a name, or remove the row.',
            'steps.*.instruction.required' => 'Write the step, or remove it.',
            'slug.regex' => 'The web address may only contain lowercase letters, numbers and hyphens.',
            'source_url.url' => 'The source must be a full http:// or https:// address.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ingredients' => $this->compact('ingredients', 'name'),
            'steps' => $this->compact('steps', 'instruction'),
            'tags' => array_values(array_unique(array_filter(
                array_map(fn ($t): string => trim((string) $t), (array) $this->input('tags', []))
            ))),
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('status') !== RecipeStatus::Published->value) {
                    return;
                }

                // A published recipe that nobody can cook is worse than a draft.
                if ($this->input('ingredients', []) === []) {
                    $validator->errors()->add('ingredients', 'Add at least one ingredient before publishing.');
                }

                if ($this->input('steps', []) === []) {
                    $validator->errors()->add('steps', 'Add at least one step before publishing.');
                }
            },
        ];
    }

    /**
     * Drop rows whose only meaningful field is empty.
     *
     * @return list<array<string, mixed>>
     */
    private function compact(string $key, string $requiredField): array
    {
        $rows = $this->input($key, []);

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(
            $rows,
            fn ($row): bool => is_array($row) && trim((string) ($row[$requiredField] ?? '')) !== ''
        ));
    }
}
