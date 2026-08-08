<?php

namespace App\Http\Requests\Library;

use App\Models\ResearchProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFavoriteRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'project_public_id' => ['nullable', 'uuid', Rule::exists(ResearchProject::class, 'public_id')],
            'note' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
