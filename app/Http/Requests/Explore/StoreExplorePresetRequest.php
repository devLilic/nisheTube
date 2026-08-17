<?php

namespace App\Http\Requests\Explore;

use Illuminate\Foundation\Http\FormRequest;

class StoreExplorePresetRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['required', 'array'],
            ...IndexExploreRequest::filterRules('filters'),
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        /** @var array<string, mixed> $filters */
        $filters = $this->validated('filters');

        return IndexExploreRequest::normalize($filters);
    }
}
