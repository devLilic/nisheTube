<?php

use App\Http\Controllers\Analyzer\AnalyzerAudienceSignalController;
use App\Http\Controllers\Analyzer\AnalyzerAudienceSignalExclusionController;
use App\Http\Controllers\Analyzer\AnalyzerCommentController;
use App\Http\Controllers\Analyzer\AnalyzerComparisonController;
use App\Http\Controllers\Analyzer\AnalyzerController;
use App\Http\Controllers\Analyzer\AnalyzerCurationController;
use App\Http\Controllers\Analyzer\AnalyzerPerformanceExportController;
use App\Http\Controllers\Analyzer\AnalyzerRunController;
use App\Http\Controllers\Analyzer\AnalyzerThumbnailController;
use App\Http\Controllers\Analyzer\AnalyzerTranscriptController;
use App\Http\Controllers\Analyzer\AnalyzerTranscriptStructureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Discovery\DiscoveryController;
use App\Http\Controllers\Discovery\DiscoveryRunController;
use App\Http\Controllers\Discovery\NicheCandidateController;
use App\Http\Controllers\Explore\ExploreController;
use App\Http\Controllers\Explore\ExplorePresetController;
use App\Http\Controllers\Exports\ExportController;
use App\Http\Controllers\History\HistoryController;
use App\Http\Controllers\Ideas\SavedCommentIdeaController;
use App\Http\Controllers\Library\FavoriteController;
use App\Http\Controllers\Library\ProjectController;
use App\Http\Controllers\Library\ShortlistController;
use App\Http\Controllers\Library\TagController;
use App\Http\Controllers\Navigation\CompletedRunNotificationController;
use App\Http\Controllers\Navigation\GlobalResearchSearchController;
use App\Http\Controllers\Research\ResearchController;
use App\Http\Controllers\Research\ResearchRunController;
use App\Http\Controllers\Topics\TopicWorkspaceController;
use App\Http\Controllers\Watchlist\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('global-research-search', GlobalResearchSearchController::class)->name('global-research-search');
    Route::patch('completed-run-notifications/read', [CompletedRunNotificationController::class, 'update'])->name('completed-run-notifications.read');
    if (app()->environment('local')) {
        Route::inertia('design-system', 'design-system')->name('design-system');
    }

    Route::get('search', [ResearchController::class, 'create'])->name('research.create');
    Route::post('search', [ResearchController::class, 'store'])->name('research.store');
    Route::get('research/runs/{researchRun}', [ResearchRunController::class, 'show'])->name('research.runs.show');
    Route::post('research/runs/{researchRun}/retry', [ResearchRunController::class, 'retry'])->name('research.runs.retry');

    Route::get('analyzer', [AnalyzerController::class, 'index'])->name('analyzer.index');
    Route::post('analyzer', [AnalyzerController::class, 'store'])->name('analyzer.store');
    Route::get('analyzer/compare', AnalyzerComparisonController::class)->name('analyzer.compare');
    Route::get('analyzer/runs/{analyzerRun}', [AnalyzerRunController::class, 'show'])->name('analyzer.runs.show');
    Route::post('analyzer/runs/{analyzerRun}/refresh', [AnalyzerRunController::class, 'refresh'])->name('analyzer.runs.refresh');
    Route::post('analyzer/runs/{analyzerRun}/comments', AnalyzerCommentController::class)->name('analyzer.runs.comments.store');
    Route::post('analyzer/runs/{analyzerRun}/audience-signals', AnalyzerAudienceSignalController::class)->name('analyzer.runs.audience-signals.store');
    Route::post('analyzer/runs/{analyzerRun}/audience-signal-exclusions', [AnalyzerAudienceSignalExclusionController::class, 'store'])->name('analyzer.runs.audience-signal-exclusions.store');
    Route::delete('analyzer/runs/{analyzerRun}/audience-signal-exclusions/{audienceSignalExclusion}', [AnalyzerAudienceSignalExclusionController::class, 'destroy'])->name('analyzer.runs.audience-signal-exclusions.destroy');
    Route::post('analyzer/runs/{analyzerRun}/transcripts', [AnalyzerTranscriptController::class, 'store'])->name('analyzer.runs.transcripts.store');
    Route::delete('analyzer/runs/{analyzerRun}/transcripts/{transcriptDocument}', [AnalyzerTranscriptController::class, 'destroy'])->name('analyzer.runs.transcripts.destroy');
    Route::post('analyzer/runs/{analyzerRun}/transcripts/{transcriptDocument}/structure', AnalyzerTranscriptStructureController::class)->name('analyzer.runs.transcripts.structure.store');
    Route::post('analyzer/runs/{analyzerRun}/thumbnails', AnalyzerThumbnailController::class)->name('analyzer.runs.thumbnails.store');
    Route::post('analyzer/runs/{analyzerRun}/performance-export', AnalyzerPerformanceExportController::class)->name('analyzer.runs.performance-export');
    Route::patch('analyzer/runs/{analyzerRun}/curation', [AnalyzerCurationController::class, 'update'])->name('analyzer.runs.curation.update');

    Route::get('explore', ExploreController::class)->name('explore.index');
    Route::post('explore/presets', [ExplorePresetController::class, 'store'])->name('explore.presets.store');
    Route::delete('explore/presets/{explorePreset}', [ExplorePresetController::class, 'destroy'])->name('explore.presets.destroy');

    Route::get('ideas', [SavedCommentIdeaController::class, 'index'])->name('ideas.index');
    Route::post('ideas/comments/{publicComment}', [SavedCommentIdeaController::class, 'store'])->name('ideas.comments.store');
    Route::delete('ideas/{savedCommentIdea}', [SavedCommentIdeaController::class, 'destroy'])->name('ideas.destroy');

    Route::get('watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::patch('watchlist/{watchlistItem}', [WatchlistController::class, 'update'])->name('watchlist.update');
    Route::post('watchlist/{watchlistItem}/refresh', [WatchlistController::class, 'refresh'])->name('watchlist.refresh');
    Route::post('watchlist/refreshes/{watchlistRefreshRun}/retry', [WatchlistController::class, 'retry'])->name('watchlist.refreshes.retry');
    Route::delete('watchlist/{watchlistItem}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    Route::get('topics', [TopicWorkspaceController::class, 'index'])->name('topics.index');
    Route::post('topics', [TopicWorkspaceController::class, 'store'])->name('topics.store');
    Route::get('topics/{topicWorkspace}', [TopicWorkspaceController::class, 'show'])->name('topics.show');
    Route::patch('topics/{topicWorkspace}', [TopicWorkspaceController::class, 'update'])->name('topics.update');
    Route::post('topics/{topicWorkspace}/archive', [TopicWorkspaceController::class, 'archive'])->name('topics.archive');
    Route::post('topics/{topicWorkspace}/restore', [TopicWorkspaceController::class, 'restore'])->name('topics.restore');
    Route::post('topics/{topicWorkspace}/evidence', [TopicWorkspaceController::class, 'addEvidence'])->name('topics.evidence.store');
    Route::delete('topics/{topicWorkspace}/evidence/{topicWorkspaceItem}', [TopicWorkspaceController::class, 'removeEvidence'])->name('topics.evidence.destroy');
    Route::post('topics/{topicWorkspace}/launch-search', [TopicWorkspaceController::class, 'launchSearch'])->name('topics.launch.search');
    Route::post('topics/{topicWorkspace}/launch-discovery', [TopicWorkspaceController::class, 'launchDiscovery'])->name('topics.launch.discovery');

    Route::get('discover', [DiscoveryController::class, 'index'])->name('discovery.index');
    Route::post('discover', [DiscoveryController::class, 'store'])->name('discovery.store');
    Route::get('discover/runs/{discoveryRun}', [DiscoveryRunController::class, 'show'])->name('discovery.runs.show');
    Route::post('discover/runs/{discoveryRun}/retry', [DiscoveryRunController::class, 'retry'])->name('discovery.runs.retry');
    Route::patch('discover/candidates/{nicheCandidate}', [NicheCandidateController::class, 'update'])->name('discovery.candidates.update');
    Route::post('discover/candidates/{nicheCandidate}/validate', [NicheCandidateController::class, 'validateCandidate'])->name('discovery.candidates.validate');

    Route::get('projects', [ProjectController::class, 'index'])->name('library.projects.index');
    Route::get('projects/{researchProject}', [ProjectController::class, 'show'])->name('library.projects.show');
    Route::get('favorites', [FavoriteController::class, 'index'])->name('library.favorites.index');
    Route::get('shortlist', ShortlistController::class)->name('shortlist.index');
    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('history/compare/{beforeRun}/{afterRun}', [HistoryController::class, 'compare'])->name('history.compare');
    Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
    Route::post('exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('exports/{researchExport}/download', [ExportController::class, 'download'])->name('exports.download');
    Route::post('exports/{researchExport}/retry', [ExportController::class, 'retry'])->name('exports.retry');
    Route::delete('exports/{researchExport}', [ExportController::class, 'destroy'])->name('exports.destroy');
    Route::post('library/projects', [ProjectController::class, 'store'])->name('library.projects.store');
    Route::patch('library/projects/{researchProject}', [ProjectController::class, 'update'])->name('library.projects.update');
    Route::post('library/projects/{researchProject}/archive', [ProjectController::class, 'archive'])->name('library.projects.archive');
    Route::post('library/projects/{researchProject}/restore', [ProjectController::class, 'restore'])->name('library.projects.restore');
    Route::delete('library/projects/{researchProject}', [ProjectController::class, 'destroy'])->name('library.projects.destroy');
    Route::post('library/favorites', [FavoriteController::class, 'store'])->name('library.favorites.store');
    Route::patch('library/favorites/{favorite}', [FavoriteController::class, 'update'])->name('library.favorites.update');
    Route::delete('library/favorites/{favorite}', [FavoriteController::class, 'destroy'])->name('library.favorites.destroy');
    Route::post('library/tags', [TagController::class, 'store'])->name('library.tags.store');
    Route::patch('library/tags/{tag}', [TagController::class, 'update'])->name('library.tags.update');
    Route::post('library/tags/{tag}/targets', [TagController::class, 'attach'])->name('library.tags.attach');
    Route::delete('library/tags/{tag}/targets', [TagController::class, 'detach'])->name('library.tags.detach');
    Route::delete('library/tags/{tag}', [TagController::class, 'destroy'])->name('library.tags.destroy');
});

require __DIR__.'/settings.php';
