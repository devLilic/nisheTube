<?php

namespace App\Http\Requests\Discovery;

use App\Domain\Discovery\Enums\NicheCandidateStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateStatusRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    NicheCandidateStatus::New->value,
                    NicheCandidateStatus::Saved->value,
                    NicheCandidateStatus::Dismissed->value,
                ]),
            ],
        ];
    }

    public function status(): NicheCandidateStatus
    {
        return NicheCandidateStatus::from((string) $this->validated('status'));
    }
}
