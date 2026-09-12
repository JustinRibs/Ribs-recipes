<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RecipeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CsvConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Opaque handle for the uploaded file held in private storage.
            'token' => ['required', 'string', 'regex:/^[0-9A-Za-z]{26}$/'],
            'rows' => ['required', 'array', 'min:1', 'max:250'],
            'rows.*' => ['integer', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['required', Rule::enum(RecipeStatus::class)],
            'download_images' => ['boolean'],
            'extra_tags' => ['array', 'max:10'],
            'extra_tags.*' => ['string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Select at least one recipe to import.',
            'token.regex' => 'That upload has expired. Upload the file again.',
        ];
    }
}
