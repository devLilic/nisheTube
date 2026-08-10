<?php

namespace App\Http\Requests\Analyzer;

use App\Domain\Analyzer\ValueObjects\YouTubeChannelReference;
use App\Domain\Analyzer\ValueObjects\YouTubeVideoReference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreAnalyzerRunRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'target_kind' => ['nullable', 'string', 'in:video,channel'],
            'target_reference' => ['nullable', 'required_without:video_reference', 'string', 'max:2048'],
            'video_reference' => ['nullable', 'required_without:target_reference', 'string', 'max:2048'],
            'origin_kind' => ['nullable', 'string', 'in:manual,search,explore,discover,watchlist,topic_workspace'],
            'origin_reference' => ['nullable', 'string', 'max:255'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('target_reference') || $validator->errors()->has('video_reference') || $validator->errors()->has('target_kind')) {
                return;
            }

            try {
                $this->targetKind() === 'channel'
                    ? YouTubeChannelReference::parse($this->targetReference())
                    : YouTubeVideoReference::parse($this->targetReference());
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add($this->input('target_reference') === null ? 'video_reference' : 'target_reference', $exception->getMessage());
            }
        }];
    }

    public function targetKind(): string
    {
        return (string) ($this->validated('target_kind') ?? 'video');
    }

    public function targetReference(): string
    {
        return (string) ($this->validated('target_reference') ?? $this->validated('video_reference'));
    }

    public function originKind(): string
    {
        return (string) ($this->validated('origin_kind') ?? 'manual');
    }

    public function originReference(): ?string
    {
        $value = $this->validated('origin_reference');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function returnTo(): ?string
    {
        $value = $this->validated('return_to');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
