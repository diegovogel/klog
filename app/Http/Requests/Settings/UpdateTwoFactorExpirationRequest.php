<?php

namespace App\Http\Requests\Settings;

use App\Services\TwoFactorConfigService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTwoFactorExpirationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'remember_days' => [
                'required',
                'integer',
                'min:'.TwoFactorConfigService::MIN_DAYS,
                'max:'.TwoFactorConfigService::MAX_DAYS,
            ],
        ];
    }
}
