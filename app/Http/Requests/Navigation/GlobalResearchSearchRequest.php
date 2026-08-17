<?php

namespace App\Http\Requests\Navigation;

use Illuminate\Foundation\Http\FormRequest;

class GlobalResearchSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->input('q'))]);
    }
}
