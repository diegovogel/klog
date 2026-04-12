<?php

namespace App\Http\Requests;

use App\Enums\MemoryType;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in(MemoryType::values())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'children' => ['nullable', 'array'],
            'children.*' => ['integer', 'exists:children,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Normalize request input into the shape SearchService expects.
     *
     * @return array{types: array<int, string>, from: Carbon|null, to: Carbon|null, children: array<int, int>, user_id: int|null}
     */
    public function filters(): array
    {
        return [
            'types' => array_values($this->validated('types', [])),
            'from' => $this->validated('from') ? Carbon::parse($this->validated('from')) : null,
            'to' => $this->validated('to') ? Carbon::parse($this->validated('to')) : null,
            'children' => array_map('intval', $this->validated('children', [])),
            'user_id' => $this->validated('user_id') ? (int) $this->validated('user_id') : null,
        ];
    }

    public function searchTerm(): string
    {
        return (string) $this->validated('q', '');
    }
}
