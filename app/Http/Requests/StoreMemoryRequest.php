<?php

namespace App\Http\Requests;

use App\Enums\MimeType;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:65535'],
            'media' => ['nullable', 'array', 'max:20'],
            'media.*' => [
                'file',
                'mimetypes:'.implode(',', MimeType::values()),
                'max:102400',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media.max' => 'You may upload a maximum of 20 files.',
            'media.*.mimetypes' => 'Each file must be a supported image, video, or audio format.',
            'media.*.max' => 'Each file must be 100 MB or smaller.',
        ];
    }
}
