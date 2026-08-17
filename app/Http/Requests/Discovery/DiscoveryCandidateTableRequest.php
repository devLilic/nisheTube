<?php

namespace App\Http\Requests\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoveryCandidateTableRequest extends FormRequest
{
    private const SORTS = [
        'evidence_score',
        'confidence',
        'videos',
        'channels',
        'small_channel_proof',
        'typical_performance',
        'stability',
        'status',
        'theme',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'candidate_sort' => ['sometimes', 'string', Rule::in(self::SORTS)],
            'candidate_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'candidate_status' => ['sometimes', 'string', Rule::in(['all', 'new', 'saved', 'dismissed', 'validated'])],
            'candidate_minimum_score' => ['sometimes', 'integer', Rule::in([0, 40, 60, 80])],
            'candidate_minimum_confidence' => ['sometimes', 'integer', Rule::in([0, 40, 60, 80])],
            'candidate_page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'weak_page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /** @return array{sort: string, direction: 'asc'|'desc', status: string, minimum_score: int, minimum_confidence: int} */
    public function tableQuery(): array
    {
        $direction = $this->validated('candidate_direction', 'desc');

        return [
            'sort' => (string) $this->validated('candidate_sort', 'evidence_score'),
            'direction' => $direction === 'asc' ? 'asc' : 'desc',
            'status' => (string) $this->validated('candidate_status', 'all'),
            'minimum_score' => (int) $this->validated('candidate_minimum_score', 0),
            'minimum_confidence' => (int) $this->validated('candidate_minimum_confidence', 0),
        ];
    }
}
