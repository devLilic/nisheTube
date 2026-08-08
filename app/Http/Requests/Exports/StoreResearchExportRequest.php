<?php

namespace App\Http\Requests\Exports;

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
            'research_run_ids' => ['required', 'array', 'min:1', "max:{$max}"],
            'research_run_ids.*' => ['required', 'uuid', 'distinct'],
            'columns' => ['required', 'array', 'min:4'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(app(ResearchExportColumns::class)->all())],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $columns = $this->input('columns');

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

    /** @return list<string> */
    public function researchRunIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('research_run_ids');

        return $ids;
    }

    /** @return list<string> */
    public function columns(): array
    {
        /** @var list<string> $columns */
        $columns = $this->validated('columns');

        return $columns;
    }
}
