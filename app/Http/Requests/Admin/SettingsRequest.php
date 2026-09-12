<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
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
            'tagline' => ['nullable', 'string', 'max:120'],
            'hero_heading' => ['nullable', 'string', 'max:120'],
            'hero_subheading' => ['nullable', 'string', 'max:240'],
            'footer_note' => ['nullable', 'string', 'max:240'],
        ];
    }
}
