<?php

namespace App\Http\Requests\Research;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResearchEvidenceRequest extends FormRequest
{
    private const SORTS = [
        'relevance',
        'views_per_day',
        'engagement',
        'channel_size',
        'reach_ratio',
        'published_at',
        'breakout_class',
    ];

    private const FILTERS = [
        'all',
        'strictly_relevant',
        'small_channels',
        'mid_size_channels',
        'large_channels',
        'breakouts',
        'long_form',
        'shorts',
        'complete_metrics',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'evidence_sort' => ['sometimes', 'string', Rule::in(self::SORTS)],
            'evidence_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'evidence_filter' => ['sometimes', 'string', Rule::in(self::FILTERS)],
            'evidence_page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'return_to' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array{sort: string, direction: string, filter: string, page: int} */
    public function evidenceQuery(): array
    {
        return [
            'sort' => $this->validated('evidence_sort', 'relevance'),
            'direction' => $this->validated('evidence_direction', 'desc'),
            'filter' => $this->validated('evidence_filter', 'all'),
            'page' => (int) $this->validated('evidence_page', 1),
        ];
    }
}
