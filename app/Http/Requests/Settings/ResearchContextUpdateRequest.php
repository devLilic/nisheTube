<?php

namespace App\Http\Requests\Settings;

use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResearchContextUpdateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'changed' => ['required', Rule::in(['market', 'project', 'workspace'])],
            'market_key' => ['nullable', Rule::exists(Market::class, 'key')->where('is_enabled', true)],
            'project' => [
                'nullable',
                'uuid',
                Rule::exists(ResearchProject::class, 'public_id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('archived_at'),
            ],
            'workspace' => [
                'nullable',
                'uuid',
                Rule::exists(TopicWorkspace::class, 'public_id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('archived_at'),
            ],
        ];
    }

    public function changed(): string
    {
        return (string) $this->validated('changed');
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
