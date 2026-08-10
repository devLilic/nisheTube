<?php

namespace App\Http\Requests\Explore;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExploreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'entity_type' => ['sometimes', Rule::in(['video', 'channel', 'candidate'])],
            'source' => ['sometimes', Rule::in(['all', 'research', 'analyzer', 'discovery', 'library'])],
            'market' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:32'],
            'topic' => ['nullable', 'string', 'max:120'],
            'breakout' => ['nullable', Rule::in(['normal', 'strong', 'breakout'])],
            'channel_size' => ['nullable', Rule::in(['small', 'mid', 'large', 'hidden'])],
            'min_performance' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'min_score' => ['nullable', 'numeric', 'between:0,100'],
            'min_confidence' => ['nullable', 'numeric', 'between:0,100'],
            'observed_from' => ['nullable', 'date_format:Y-m-d'],
            'observed_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:observed_from'],
            'organization' => ['sometimes', Rule::in(['all', 'favorite', 'not_favorite', 'curated', 'unreviewed'])],
            'sort' => ['sometimes', Rule::in(['latest', 'score_desc', 'performance_desc', 'title'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'entity_type' => $validated['entity_type'] ?? 'video',
            'source' => $validated['source'] ?? 'all',
            'market' => $validated['market'] ?? null,
            'category' => $validated['category'] ?? null,
            'topic' => $validated['topic'] ?? null,
            'breakout' => $validated['breakout'] ?? null,
            'channel_size' => $validated['channel_size'] ?? null,
            'min_performance' => isset($validated['min_performance']) ? (float) $validated['min_performance'] : null,
            'min_score' => isset($validated['min_score']) ? (float) $validated['min_score'] : null,
            'min_confidence' => isset($validated['min_confidence']) ? (float) $validated['min_confidence'] : null,
            'observed_from' => $validated['observed_from'] ?? null,
            'observed_to' => $validated['observed_to'] ?? null,
            'organization' => $validated['organization'] ?? 'all',
            'sort' => $validated['sort'] ?? 'latest',
        ];
    }
}
