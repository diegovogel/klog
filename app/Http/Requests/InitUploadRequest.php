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
        $mimeType = $this->input('mime_type');
        $filename = $this->input('original_filename');

        // Non-string input is left untouched so the validation rules reject it with a 422.
        if ($mimeType !== null && ! is_string($mimeType)) {
            return;
        }

        // Browsers sometimes report an empty type for files picked from the iOS Files app.
        if (($mimeType ?? '') === '' && is_string($filename) && $filename !== '') {
            $mimeType = MimeType::fromExtension(pathinfo($filename, PATHINFO_EXTENSION))?->value;
        }

        if (is_string($mimeType) && $mimeType !== '') {
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
