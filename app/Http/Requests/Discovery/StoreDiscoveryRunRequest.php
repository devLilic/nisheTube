<?php

namespace App\Http\Requests\Discovery;

use App\Models\Market;
use App\Models\ResearchRun;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscoveryRunRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'market_key' => [
                'required',
                'string',
                Rule::exists(Market::class, 'key')->where('is_enabled', true),
            ],
            'sample_per_seed' => ['required', 'integer', Rule::in([10, 25, 50])],
            'candidate_limit' => ['required', 'integer', Rule::in([10, 20])],
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

    /** @return list<array{query: string, research_run_id: string}> */
    public function seeds(): array
    {
        /** @var list<array{query: string, research_run_id: string}> $seeds */
        $seeds = $this->validated('seeds');

        return $seeds;
    }
}
