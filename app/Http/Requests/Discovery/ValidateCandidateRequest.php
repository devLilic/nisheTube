<?php

namespace App\Http\Requests\Discovery;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateCandidateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'requested_result_count' => ['required', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    public function requestedResultCount(): int
    {
        return (int) $this->validated('requested_result_count');
    }
}
