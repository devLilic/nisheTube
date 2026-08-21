<?php

use App\Http\Controllers\Settings\PreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ResearchContextController;
use App\Http\Controllers\Settings\RetentionController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\YouTubeIntegrationController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::put('research-context', [ResearchContextController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('research-context.update');
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:12,1')
        ->name('profile.update');
    Route::get('settings/preferences', [PreferencesController::class, 'edit'])
        ->name('preferences.edit');
    Route::put('settings/preferences', [PreferencesController::class, 'update'])
        ->middleware('throttle:12,1')
        ->name('preferences.update');
    Route::get('settings/youtube', [YouTubeIntegrationController::class, 'edit'])
        ->name('youtube.edit');
    Route::post('settings/youtube/test', [YouTubeIntegrationController::class, 'test'])
        ->middleware('throttle:3,1')
        ->name('youtube.test');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:3,1')
        ->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/retention', [RetentionController::class, 'index'])->name('retention.index');
    Route::post('settings/retention/preview', [RetentionController::class, 'preview'])->name('retention.preview');
    Route::post('settings/retention/cleanup', [RetentionController::class, 'cleanup'])
        ->middleware('throttle:3,1')
        ->name('retention.cleanup');
    Route::delete('settings/retention/runs', [RetentionController::class, 'destroy'])
        ->middleware('throttle:3,1')
        ->name('retention.runs.destroy');
});
