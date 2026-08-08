<?php

namespace App\Http\Requests\Library;

use App\Domain\Library\Enums\LibraryTargetType;
use App\Models\ResearchProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoriteRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::enum(LibraryTargetType::class)],
            'target_reference' => ['required', 'string', 'max:500'],
            'project_public_id' => ['nullable', 'uuid', Rule::exists(ResearchProject::class, 'public_id')],
            'note' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function targetType(): LibraryTargetType
    {
        return LibraryTargetType::from((string) $this->validated('target_type'));
    }
}
