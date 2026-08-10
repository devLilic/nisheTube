<?php

namespace App\Http\Requests\Analyzer;

use Illuminate\Foundation\Http\FormRequest;

class CompareAnalyzerChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'before' => ['nullable', 'uuid', 'different:after', 'different:third'],
            'after' => ['nullable', 'uuid', 'different:before', 'different:third'],
            'third' => ['nullable', 'uuid', 'different:before', 'different:after'],
        ];
    }

    public function beforeId(): ?string
    {
        return $this->validated('before');
    }

    public function afterId(): ?string
    {
        return $this->validated('after');
    }

    public function thirdId(): ?string
    {
        return $this->validated('third');
    }

    /** @return list<string> */
    public function selectedIds(): array
    {
        return array_values(array_filter([
            $this->beforeId(),
            $this->afterId(),
            $this->thirdId(),
        ]));
    }
}
