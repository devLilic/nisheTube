<?php

namespace App\Http\Requests\Discovery;

use App\Domain\Discovery\Actions\BulkDismissCandidates;
use Illuminate\Foundation\Http\FormRequest;

final class BulkDismissCandidatesRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'candidate_ids' => ['required', 'array', 'min:1', 'max:'.BulkDismissCandidates::MAX_SELECTION],
            'candidate_ids.*' => ['required', 'string', 'uuid', 'distinct:strict'],
        ];
    }

    /** @return list<string> */
    public function candidatePublicIds(): array
    {
        return array_values($this->validated('candidate_ids'));
    }
}
