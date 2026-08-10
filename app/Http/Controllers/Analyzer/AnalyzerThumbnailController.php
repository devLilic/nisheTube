<?php

namespace App\Http\Controllers\Analyzer;

use App\Domain\Thumbnails\Actions\CreateThumbnailAnalysis;
use App\Http\Controllers\Controller;
use App\Models\AnalyzerRun;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AnalyzerThumbnailController extends Controller
{
    public function __invoke(Request $request, AnalyzerRun $analyzerRun, CreateThumbnailAnalysis $create): RedirectResponse
    {
        Gate::authorize('analyzeThumbnails', $analyzerRun);

        try {
            $profile = $create->handle($request->user(), $analyzerRun);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $profile->status->isActive()
                    ? __('Thumbnail analysis queued.')
                    : __('The existing immutable thumbnail analysis is already available.'),
            ]);
        } catch (DomainException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getMessage())]);
        }

        return to_route('analyzer.runs.show', $analyzerRun);
    }
}
