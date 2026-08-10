<?php

namespace App\Http\Requests\Analyzer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefreshAnalyzerRunRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['mode' => ['required', Rule::in(['allow_cache', 'force_refresh'])]];
    }

    public function forceRefresh(): bool
    {
        return $this->validated('mode') === 'force_refresh';
    }
}
