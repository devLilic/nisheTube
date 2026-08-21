<?php

namespace App\Http\Requests\Library;

use App\Models\Market;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'market_key' => ['nullable', 'string', Rule::exists(Market::class, 'key')->where('is_enabled', true)],
            'themes' => ['nullable', 'string', 'max:1000'],
            'decision_status' => ['nullable', 'in:exploring,active,decided,paused'],
            'decision_note' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
