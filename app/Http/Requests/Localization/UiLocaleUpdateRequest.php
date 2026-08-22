<?php

namespace App\Http\Requests\Localization;

use App\Domain\Localization\Enums\UiLocale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UiLocaleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'ui_locale' => ['required', 'string', Rule::in(UiLocale::values())],
        ];
    }

    public function uiLocale(): UiLocale
    {
        return UiLocale::from((string) $this->validated('ui_locale'));
    }
}
