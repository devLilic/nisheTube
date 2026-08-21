<?php

namespace App\Http\Requests\History;

use App\Domain\Research\Enums\ResearchRunStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'anchor' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:500'],
            'market' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', Rule::enum(ResearchRunStatus::class)],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'project' => ['nullable', 'uuid'],
            'workspace' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ];
    }

    /** @return array<string, mixed> */
    public function historyFilters(): array
    {
        return [
            'q' => $this->validated('q', ''),
            'market' => $this->validated('market'),
            'status' => $this->validated('status'),
            'min_score' => $this->validated('min_score'),
            'min_confidence' => $this->validated('min_confidence'),
            'date_from' => $this->validated('date_from'),
            'date_to' => $this->validated('date_to'),
            'project' => $this->validated('project'),
            'workspace' => $this->validated('workspace'),
            'page' => (int) $this->validated('page', 1),
            'per_page' => (int) $this->validated('per_page', 25),
        ];
    }
}
