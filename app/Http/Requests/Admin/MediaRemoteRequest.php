<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaRemoteRequest extends FormRequest
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
            'url' => ['required', 'url:http,https', 'max:2048'],
            // "download" copies the file onto the media disk; "link" keeps the
            // original URL and hot-links it.
            'mode' => ['required', Rule::in(['download', 'link'])],
            'alt' => ['nullable', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:300'],
        ];
    }
}
