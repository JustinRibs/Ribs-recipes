<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CsvUploadRequest extends FormRequest
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
                'max:'.(int) config('ribs.csv.max_upload_kb'),
                // Spreadsheet apps are inconsistent about the CSV media type,
                // so the extension is checked too rather than instead.
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,text/comma-separated-values',
                'extensions:csv,txt',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'That does not look like a CSV file. Export as CSV and try again.',
            'file.extensions' => 'The file needs a .csv extension.',
        ];
    }
}
