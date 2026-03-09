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

    protected function prepareForValidation(): void
    {
        if ($this->has('clippings')) {
            $this->merge([
                'clippings' => array_values(array_filter(
                    $this->input('clippings', []),
                    fn ($url) => filled($url),
                )),
            ]);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'memory_date' => ['required', 'date', 'before_or_equal:today'],
            'content' => ['nullable', 'string', 'max:65535'],
            'clippings' => ['nullable', 'array'],
            'clippings.*' => ['required', 'url:http,https', 'max:2048'],
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
            'clippings.*.required' => 'Each clipping must have a URL.',
            'clippings.*.url' => 'Each clipping must be a valid URL.',
            'clippings.*.max' => 'Each URL must be 2048 characters or fewer.',
            'media.max' => 'You may upload a maximum of 20 files.',
            'media.*.mimetypes' => 'Each file must be a supported image, video, or audio format.',
            'media.*.max' => 'Each file must be 100 MB or smaller.',
        ];
    }
}
