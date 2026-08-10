<?php

namespace App\Http\Requests\Analyzer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnalyzerCurationRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'subject_type' => ['required', Rule::in(['video', 'channel'])],
            'research_status' => ['required', Rule::in(['unreviewed', 'researching', 'promising', 'ruled_out'])],
            'note' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
