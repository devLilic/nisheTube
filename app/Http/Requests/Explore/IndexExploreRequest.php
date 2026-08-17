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
        return self::filterRules();
    }

    /** @return array<string, list<mixed>> */
    public static function filterRules(string $prefix = ''): array
    {
        $key = fn (string $name): string => $prefix === '' ? $name : $prefix.'.'.$name;

        return [
            $key('entity_type') => ['sometimes', Rule::in(['video', 'channel', 'candidate'])],
            $key('source') => ['sometimes', Rule::in(['all', 'research', 'analyzer', 'discovery', 'library'])],
            $key('market') => ['nullable', 'string', 'max:32'],
            $key('category') => ['nullable', 'string', 'max:32'],
            $key('topic') => ['nullable', 'string', 'max:120'],
            $key('breakout') => ['nullable', Rule::in(['normal', 'strong', 'breakout'])],
            $key('channel_size') => ['nullable', Rule::in(['small', 'mid', 'large', 'hidden'])],
            $key('min_performance') => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            $key('min_score') => ['nullable', 'numeric', 'between:0,100'],
            $key('min_confidence') => ['nullable', 'numeric', 'between:0,100'],
            $key('observed_from') => ['nullable', 'date_format:Y-m-d'],
            $key('observed_to') => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$key('observed_from')],
            $key('organization') => ['sometimes', Rule::in(['all', 'favorite', 'not_favorite', 'curated', 'unreviewed'])],
            $key('sort') => ['sometimes', Rule::in(['latest', 'score_desc', 'performance_desc', 'title'])],
            $key('page') => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return self::normalize($this->validated());
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public static function normalize(array $validated): array
    {

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
