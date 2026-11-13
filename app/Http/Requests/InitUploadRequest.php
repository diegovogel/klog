<?php

namespace App\Http\Requests;

use App\Enums\MimeType;
use Illuminate\Foundation\Http\FormRequest;

class InitUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mimeType = (string) $this->input('mime_type', '');

        // Browsers sometimes report an empty type for files picked from the iOS Files app.
        if ($mimeType === '' && $this->filled('original_filename')) {
            $mimeType = MimeType::fromExtension(pathinfo($this->input('original_filename'), PATHINFO_EXTENSION))?->value ?? '';
        }

        if ($mimeType !== '') {
            $this->merge(['mime_type' => MimeType::normalize($mimeType)]);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $maxFileSize = config('klog.uploads.max_file_size', 500 * 1024 * 1024);

        return [
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:'.implode(',', MimeType::values())],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.$maxFileSize],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = (int) (config('klog.uploads.max_file_size', 500 * 1024 * 1024) / 1024 / 1024);

        return [
            'mime_type.in' => 'The file type is not supported.',
            'total_size.max' => "Each file must be {$maxMb} MB or smaller.",
        ];
    }
}
