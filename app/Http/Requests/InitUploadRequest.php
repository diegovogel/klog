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
        // Strip codec parameters from MIME types (e.g. "audio/mp4;codecs=mp4a.40.2" → "audio/mp4").
        // iOS Safari's MediaRecorder reports MIME types with codec suffixes.
        if ($this->has('mime_type') && str_contains($this->input('mime_type'), ';')) {
            $this->merge([
                'mime_type' => trim(explode(';', $this->input('mime_type'))[0]),
            ]);
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
