<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'recovery' => ['sometimes', 'boolean'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
