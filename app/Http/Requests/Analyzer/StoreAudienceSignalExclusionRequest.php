<?php

namespace App\Http\Requests\Analyzer;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAudienceSignalExclusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'word' => ['required', 'string', 'max:64', 'regex:/^[\p{L}\p{N}][\p{L}\p{N}-]*$/u'],
        ];
    }

    public function word(): string
    {
        return (string) $this->validated('word');
    }
}
