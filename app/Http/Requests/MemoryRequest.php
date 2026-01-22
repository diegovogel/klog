<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['nullable'],
            'content' => ['nullable'],
            'type' => ['nullable'],
            'captured_at' => ['nullable', 'date'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
