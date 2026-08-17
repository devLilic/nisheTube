<?php

namespace App\Http\Requests\Discovery;

use App\Models\Market;
use App\Models\ResearchRun;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiscoveryRunRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'submission_token' => ['nullable', 'uuid'],
            'market_key' => [
                'required',
                'string',
                Rule::exists(Market::class, 'key')->where('is_enabled', true),
            ],
            'sample_per_seed' => ['required', 'integer', Rule::in([10, 25, 50])],
            'candidate_limit' => ['required', 'integer', Rule::in([10, 20])],
            'language' => ['nullable', Rule::in(['en', 'ro', 'ru'])],
            'content_format' => ['nullable', Rule::in(['any', 'mixed', 'long_form', 'shorts'])],
            'period' => ['nullable', Rule::in(['past_week', 'past_month', 'past_three_months', 'past_year', 'any'])],
            'target_channel_size' => ['nullable', Rule::in(['any', 'small', 'mid_size', 'large'])],
            'seeds' => ['required', 'array', 'min:1', 'max:10'],
            'seeds.*.query' => ['required', 'string', 'min:2', 'max:500', 'distinct:ignore_case'],
            'seeds.*.research_run_id' => [
                'required',
                'uuid',
                Rule::exists(ResearchRun::class, 'public_id'),
                'distinct',
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $market = Market::query()->where('key', $this->input('market_key'))->first();
            $language = $this->input('language');

            if ($market !== null && is_string($language) && $language !== $market->relevance_language) {
                $validator->errors()->add('language', 'The language must match the selected market.');
            }
        }];
    }

    public function marketKey(): string
    {
        return (string) $this->validated('market_key');
    }

    public function samplePerSeed(): int
    {
        return (int) $this->validated('sample_per_seed');
    }

    public function candidateLimit(): int
    {
        return (int) $this->validated('candidate_limit');
    }

    public function submissionToken(): ?string
    {
        $value = $this->validated('submission_token');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array{language: string, content_format: string, period: string, target_channel_size: string} */
    public function intakeContext(): array
    {
        $marketLanguage = Market::query()->where('key', $this->marketKey())->value('relevance_language');

        return [
            'language' => (string) ($this->validated('language') ?? (is_string($marketLanguage) ? $marketLanguage : 'en')),
            'content_format' => (string) ($this->validated('content_format') ?? 'any'),
            'period' => (string) ($this->validated('period') ?? 'past_three_months'),
            'target_channel_size' => (string) ($this->validated('target_channel_size') ?? 'any'),
        ];
    }

    /** @return list<array{query: string, research_run_id: string}> */
    public function seeds(): array
    {
        /** @var list<array{query: string, research_run_id: string}> $seeds */
        $seeds = $this->validated('seeds');

        return $seeds;
    }
}
