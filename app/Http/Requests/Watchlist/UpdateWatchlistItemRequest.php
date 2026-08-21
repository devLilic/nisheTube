<?php

namespace App\Http\Requests\Watchlist;

use App\Domain\Watchlist\Enums\WatchlistStatus;
use App\Models\TopicWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWatchlistItemRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(WatchlistStatus::class)],
            'is_active' => ['required', 'boolean'],
            'notify_on_refresh' => ['sometimes', 'boolean'],
            'project' => ['nullable', 'string', 'uuid'],
            'workspace' => ['nullable', 'uuid', Rule::exists(TopicWorkspace::class, 'public_id')->where('user_id', $this->user()->id)->whereNull('archived_at')],
            'note' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function note(): ?string
    {
        $value = trim((string) ($this->validated('note') ?? ''));

        return $value === '' ? null : $value;
    }
}
