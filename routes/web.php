<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Discovery\DiscoveryController;
use App\Http\Controllers\Discovery\DiscoveryRunController;
use App\Http\Controllers\Discovery\NicheCandidateController;
use App\Http\Controllers\Exports\ExportController;
use App\Http\Controllers\History\HistoryController;
use App\Http\Controllers\Library\FavoriteController;
use App\Http\Controllers\Library\ProjectController;
use App\Http\Controllers\Library\TagController;
use App\Http\Controllers\Research\ResearchController;
use App\Http\Controllers\Research\ResearchRunController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::inertia('design-system', 'design-system')->name('design-system');

    Route::get('search', [ResearchController::class, 'create'])->name('research.create');
    Route::post('search', [ResearchController::class, 'store'])->name('research.store');
    Route::get('research/runs/{researchRun}', [ResearchRunController::class, 'show'])->name('research.runs.show');
    Route::post('research/runs/{researchRun}/retry', [ResearchRunController::class, 'retry'])->name('research.runs.retry');

    Route::get('discover', [DiscoveryController::class, 'index'])->name('discovery.index');
    Route::post('discover', [DiscoveryController::class, 'store'])->name('discovery.store');
    Route::get('discover/runs/{discoveryRun}', [DiscoveryRunController::class, 'show'])->name('discovery.runs.show');
    Route::post('discover/runs/{discoveryRun}/retry', [DiscoveryRunController::class, 'retry'])->name('discovery.runs.retry');
    Route::patch('discover/candidates/{nicheCandidate}', [NicheCandidateController::class, 'update'])->name('discovery.candidates.update');
    Route::post('discover/candidates/{nicheCandidate}/validate', [NicheCandidateController::class, 'validateCandidate'])->name('discovery.candidates.validate');

    Route::get('projects', [ProjectController::class, 'index'])->name('library.projects.index');
    Route::get('projects/{researchProject}', [ProjectController::class, 'show'])->name('library.projects.show');
    Route::get('favorites', [FavoriteController::class, 'index'])->name('library.favorites.index');
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
