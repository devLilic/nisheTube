<?php

namespace App\Http\Requests\Library;

use App\Domain\Library\Enums\LibraryTargetType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagTargetRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::enum(LibraryTargetType::class)],
            'target_reference' => ['required', 'string', 'max:500'],
        ];
    }

    public function targetType(): LibraryTargetType
    {
        return LibraryTargetType::from((string) $this->validated('target_type'));
    }
}
