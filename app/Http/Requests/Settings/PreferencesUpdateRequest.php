<?php

namespace App\Http\Requests\Settings;

use App\Models\Market;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferencesUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'timezone:all'],
            'default_market_key' => [
                'nullable',
                Rule::exists(Market::class, 'key')->where('is_enabled', true),
            ],
            'default_result_depth' => ['sometimes', 'required', 'integer', Rule::in([25, 50, 100, 200])],
        ];
    }

    public function timezone(): string
    {
        return (string) $this->validated('timezone');
    }

    public function defaultMarketKey(): ?string
    {
        $defaultMarketKey = $this->validated('default_market_key');

        return is_string($defaultMarketKey) ? $defaultMarketKey : null;
    }

    public function defaultResultDepth(): ?int
    {
        $defaultResultDepth = $this->validated('default_result_depth');

        return is_int($defaultResultDepth) ? $defaultResultDepth : null;
    }
}
