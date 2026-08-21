<?php

namespace App\Http\Requests\Ideas;

use App\Models\NicheCandidate;
use App\Models\TopicWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSavedCommentIdeaRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'workspace' => ['nullable', 'uuid'],
            'candidate' => ['nullable', 'uuid'],
            'decision_status' => ['required', 'in:new,reviewing,selected,ruled_out'],
            'format' => ['nullable', 'string', 'max:120'],
            'audience' => ['nullable', 'string', 'max:240'],
            'decision_note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $workspace = $this->workspace();
            $candidate = $this->candidate();
            if ($this->filled('workspace') && $workspace === null) {
                $validator->errors()->add('workspace', 'Choose an available workspace from your account.');
            }
            if ($this->filled('candidate') && $candidate === null) {
                $validator->errors()->add('candidate', 'Choose an available candidate from your account.');
            }
            if ($workspace !== null && $candidate !== null && $workspace->market_key !== $candidate->discoveryRun->market_key) {
                $validator->errors()->add('candidate', 'The candidate market must match the selected workspace.');
            }
        }];
    }

    public function workspace(): ?TopicWorkspace
    {
        $publicId = $this->input('workspace');

        return ! is_string($publicId) || $publicId === '' ? null : TopicWorkspace::query()
            ->where('user_id', $this->user()->id)->whereNull('archived_at')->where('public_id', $publicId)->first();
    }

    public function candidate(): ?NicheCandidate
    {
        $publicId = $this->input('candidate');

        return ! is_string($publicId) || $publicId === '' ? null : NicheCandidate::query()
            ->with('discoveryRun')->where('public_id', $publicId)
            ->whereHas('discoveryRun', fn ($runs) => $runs->where('user_id', $this->user()->id))->first();
    }
}
