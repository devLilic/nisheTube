<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Exports\Actions\CreateSemanticPerformanceExport;
use App\Domain\Exports\Enums\ExportFormat;
use App\Http\Controllers\Controller;
use App\Models\AnalyzerRun;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class AnalyzerPerformanceExportController extends Controller
{
    public function __invoke(Request $request, AnalyzerRun $analyzerRun, CreateSemanticPerformanceExport $create): RedirectResponse
    {
        Gate::authorize('view', $analyzerRun);
        $validated = $request->validate([
            'format' => ['required', Rule::enum(ExportFormat::class)],
        ]);

        try {
            $create->handle($request->user(), $analyzerRun, ExportFormat::from($validated['format']));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['format' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Semantic performance export queued.')]);

        return to_route('exports.index');
    }
}
