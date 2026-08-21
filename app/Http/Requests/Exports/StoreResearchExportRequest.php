<?php

namespace App\Http\Requests\Exports;

use App\Domain\Exports\Data\ExportSelectionInput;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Services\ResearchExportColumns;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResearchExportRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $max = max(1, (int) config('exports.max_research_runs', 100));

        return [
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'source_type' => ['required', Rule::in(['research_runs', 'shortlist', 'comparison', 'topic_workspace'])],
            'source_id' => ['nullable', 'uuid', 'required_if:source_type,topic_workspace'],
            'research_run_ids' => ['nullable', 'array', "max:{$max}", 'required_if:source_type,research_runs,comparison'],
            'research_run_ids.*' => ['required', 'uuid', 'distinct'],
            'video_ids' => ['nullable', 'array', 'max:500'],
            'video_ids.*' => ['required', 'string', 'max:128', 'distinct'],
            'include_technical_details' => ['required', 'boolean'],
            'confirmed' => ['accepted'],
            'columns' => ['nullable', 'array', 'min:4'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(app(ResearchExportColumns::class)->all())],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $columns = $this->input('columns');

            if ($columns === null) {
                return;
            }

            if (! is_array($columns)) {
                return;
            }

            $required = app(ResearchExportColumns::class)->required();

            if (array_diff($required, $columns) !== []) {
                $validator->errors()->add('columns', 'Run ID, query, market, and completion time are required.');
            }
        }];
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::from((string) $this->validated('format'));
    }

    public function selection(): ExportSelectionInput
    {
        /** @var list<string> $runIds */
        $runIds = $this->validated('research_run_ids', []);
        /** @var list<string> $videoIds */
        $videoIds = $this->validated('video_ids', []);
        /** @var list<string>|null $columns */
        $columns = $this->validated('columns');

        return new ExportSelectionInput(
            (string) $this->validated('source_type'), $runIds,
            $this->validated('source_id'), $videoIds,
            (bool) $this->validated('include_technical_details'),
            (bool) $this->validated('confirmed'), $columns,
        );
    }
}
