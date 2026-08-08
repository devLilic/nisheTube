<?php

namespace App\Http\Requests\Research;

use App\Domain\Research\Enums\PublishedWindow;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Models\Market;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResearchRunRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'query_text' => ['required', 'string', 'max:500'],
            'market_key' => [
                'required',
                'string',
                Rule::exists(Market::class, 'key')->where('is_enabled', true),
            ],
            'requested_result_count' => ['required', 'integer', Rule::in([25, 50, 100, 200])],
            'search_order' => ['required', Rule::enum(SearchOrder::class)],
            'published_window' => ['required', Rule::enum(PublishedWindow::class)],
            'published_after' => [
                Rule::requiredIf($this->input('published_window') === PublishedWindow::Custom->value),
                'nullable',
                'date_format:Y-m-d',
            ],
            'published_before' => [
                Rule::requiredIf($this->input('published_window') === PublishedWindow::Custom->value),
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:published_after',
            ],
            'video_duration' => ['required', Rule::enum(VideoDurationFilter::class)],
            'video_category_id' => ['nullable', 'regex:/^[0-9]{1,32}$/'],
        ];
    }

    public function queryText(): string
    {
        return (string) $this->validated('query_text');
    }

    public function marketKey(): string
    {
        return (string) $this->validated('market_key');
    }

    public function requestedResultCount(): int
    {
        return (int) $this->validated('requested_result_count');
    }

    public function searchOrder(): SearchOrder
    {
        return SearchOrder::from((string) $this->validated('search_order'));
    }

    public function publishedWindow(): PublishedWindow
    {
        return PublishedWindow::from((string) $this->validated('published_window'));
    }

    public function publishedAfter(): ?string
    {
        return $this->validatedStringOrNull('published_after');
    }

    public function publishedBefore(): ?string
    {
        return $this->validatedStringOrNull('published_before');
    }

    public function videoDuration(): VideoDurationFilter
    {
        return VideoDurationFilter::from((string) $this->validated('video_duration'));
    }

    public function videoCategoryId(): ?string
    {
        return $this->validatedStringOrNull('video_category_id');
    }

    private function validatedStringOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
