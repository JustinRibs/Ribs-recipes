<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                // `image` checks the real contents, not the filename, and the
                // bytes are re-decoded again before anything is stored.
                'image',
                'mimetypes:'.implode(',', (array) config('ribs.images.accepted_mimes')),
                'max:'.(int) config('ribs.images.max_upload_kb'),
            ],
            'alt' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mb = round(((int) config('ribs.images.max_upload_kb')) / 1024);

        return [
            'file.max' => "That photo is larger than {$mb} MB.",
            'file.image' => 'That file is not a photo.',
            'file.mimetypes' => 'Photos must be JPEG, PNG, WebP, AVIF or HEIC.',
        ];
    }
}
