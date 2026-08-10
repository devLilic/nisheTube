<?php

namespace App\Http\Requests\Topics;

use App\Models\Market;
use App\Models\ResearchProject;
use App\Models\TopicWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTopicWorkspaceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name_key' => Str::lower(Str::squish((string) $this->input('name')))]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $workspace = $this->route('topicWorkspace');
        $workspaceId = $workspace instanceof TopicWorkspace ? $workspace->id : null;
        $marketRule = $workspace instanceof TopicWorkspace
            ? Rule::in([$workspace->market_key])
            : Rule::exists(Market::class, 'key')->where('is_enabled', true);

        return [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'name_key' => ['required', 'string', Rule::unique(TopicWorkspace::class, 'name_key')->where(fn ($query) => $query->where('user_id', $userId)->where('market_key', $this->input('market_key')))->ignore($workspaceId)],
            'description' => ['nullable', 'string', 'max:10000'],
            'market_key' => ['required', 'string', $marketRule],
            'project' => ['nullable', 'uuid', Rule::exists(ResearchProject::class, 'public_id')->where('user_id', $userId)->whereNull('archived_at')],
        ];
    }

    public function description(): ?string
    {
        $value = trim((string) ($this->validated('description') ?? ''));

        return $value === '' ? null : $value;
    }
}
