<?php

use App\Http\Controllers\Research\ResearchController;
use App\Http\Controllers\Research\ResearchRunController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('design-system', 'design-system')->name('design-system');

    Route::get('search', [ResearchController::class, 'create'])->name('research.create');
    Route::post('search', [ResearchController::class, 'store'])->name('research.store');
    Route::get('research/runs/{researchRun}', [ResearchRunController::class, 'show'])->name('research.runs.show');
    Route::post('research/runs/{researchRun}/retry', [ResearchRunController::class, 'retry'])->name('research.runs.retry');
});

require __DIR__.'/settings.php';
