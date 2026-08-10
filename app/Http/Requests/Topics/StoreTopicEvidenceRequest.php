<?php

namespace App\Http\Requests\Topics;

use App\Domain\Topics\Enums\TopicEvidenceRole;
use App\Domain\Topics\Enums\TopicEvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopicEvidenceRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::enum(TopicEvidenceType::class)],
            'target_reference' => ['required', 'string', 'max:255'],
            'evidence_role' => ['required', Rule::enum(TopicEvidenceRole::class)],
            'note' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function type(): TopicEvidenceType
    {
        return TopicEvidenceType::from((string) $this->validated('target_type'));
    }

    public function role(): TopicEvidenceRole
    {
        return TopicEvidenceRole::from((string) $this->validated('evidence_role'));
    }

    public function note(): ?string
    {
        $value = trim((string) ($this->validated('note') ?? ''));

        return $value === '' ? null : $value;
    }
}
