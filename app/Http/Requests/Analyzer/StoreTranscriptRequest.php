<?php

namespace App\Http\Requests\Analyzer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTranscriptRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'transcript' => ['required', 'string', 'max:250000'],
            'language' => ['required', Rule::in(['en', 'ro', 'ru', 'und'])],
            'rights_confirmed' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'rights_confirmed.accepted' => __('Confirm that you have the right to use this transcript.'),
            'transcript.max' => __('The pasted transcript may not exceed 250,000 characters.'),
        ];
    }
}
