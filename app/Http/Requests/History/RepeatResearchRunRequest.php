<?php

namespace App\Http\Requests\History;

use Illuminate\Foundation\Http\FormRequest;

class RepeatResearchRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function submissionToken(): string
    {
        return (string) $this->validated('submission_token');
    }
}
