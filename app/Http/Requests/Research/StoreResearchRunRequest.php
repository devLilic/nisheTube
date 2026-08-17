<?php

namespace App\Http\Requests\Research;

use App\Domain\Research\Enums\PublishedWindow;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Research\Enums\VideoDurationFilter;
use App\Models\Market;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResearchRunRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'submission_token' => ['nullable', 'uuid'],
            'workflow_mode' => ['nullable', Rule::in(['validate_idea'])],
            'preset_key' => ['nullable', Rule::in([
                'fast_scan', 'balanced', 'deep_validation', 'trend_check',
                'emerging_trend', 'evergreen_check', 'small_channel_opportunity',
                'long_form_documentary', 'shorts_opportunity', 'custom',
            ])],
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
            'language' => ['nullable', Rule::in(['en', 'ro', 'ru'])],
            'content_format' => ['nullable', Rule::in(['any', 'mixed', 'long_form', 'shorts'])],
            'target_channel_size' => ['nullable', Rule::in(['any', 'small', 'mid_size', 'large'])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $market = Market::query()->where('key', $this->input('market_key'))->first();
            $language = $this->input('language');

            if ($market !== null && is_string($language) && $language !== $market->relevance_language) {
                $validator->errors()->add('language', 'The language must match the selected market.');
            }
        }];
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

    public function submissionToken(): ?string
    {
        return $this->validatedStringOrNull('submission_token');
    }

    /** @return array{workflow_mode: string, preset_key: string, language: string, content_format: string, target_channel_size: string} */
    public function intakeContext(): array
    {
        $marketLanguage = Market::query()->where('key', $this->marketKey())->value('relevance_language');

        return [
            'workflow_mode' => $this->validatedStringOrNull('workflow_mode') ?? 'validate_idea',
            'preset_key' => $this->validatedStringOrNull('preset_key') ?? 'custom',
            'language' => $this->validatedStringOrNull('language') ?? (is_string($marketLanguage) ? $marketLanguage : 'en'),
            'content_format' => $this->validatedStringOrNull('content_format') ?? 'any',
            'target_channel_size' => $this->validatedStringOrNull('target_channel_size') ?? 'any',
        ];
    }

    private function validatedStringOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
