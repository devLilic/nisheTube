<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteResearchSnapshotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'research_run_ids' => ['required', 'array', 'min:1', 'max:100'],
            'research_run_ids.*' => ['required', 'uuid', 'distinct'],
            'confirmation' => ['required', 'accepted'],
            'favorite_impact_confirmed' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<string> */
    public function researchRunIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('research_run_ids');

        return $ids;
    }
}
