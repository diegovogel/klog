<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelUploadRequest extends FormRequest
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
        return [];
    }
}
