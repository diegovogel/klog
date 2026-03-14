<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('uploadSession')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $maxIndex = $this->route('uploadSession')->total_chunks - 1;

        return [
            'chunk' => ['required', 'file', 'max:5120'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:'.$maxIndex],
        ];
    }
}
