<?php

namespace App\Http\Requests\Watchlist;

use App\Domain\Library\Enums\LibraryTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWatchlistItemRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['video', 'channel'])],
            'target_reference' => ['required', 'string', 'max:255'],
            'project' => ['nullable', 'string', 'uuid'],
            'note' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function targetType(): LibraryTargetType
    {
        return LibraryTargetType::from((string) $this->validated('target_type'));
    }

    public function note(): ?string
    {
        $value = trim((string) ($this->validated('note') ?? ''));

        return $value === '' ? null : $value;
    }
}
