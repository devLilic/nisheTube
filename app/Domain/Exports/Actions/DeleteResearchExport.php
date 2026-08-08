<?php

namespace App\Domain\Exports\Actions;

use App\Models\ResearchExport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class DeleteResearchExport
{
    /** @throws AuthorizationException */
    public function handle(User $user, ResearchExport $export): void
    {
        if ($export->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $disk = $export->disk;
        $path = $export->path;
        $quarantinePath = null;

        if ($disk !== null && $path !== null && Storage::disk($disk)->exists($path)) {
            $quarantinePath = 'exports/.deleting/'.$export->public_id.'-'.$export->id;

            if (! Storage::disk($disk)->move($path, $quarantinePath)) {
                throw new RuntimeException('The export file could not be prepared for safe deletion.');
            }
        }

        try {
            $export->delete();
        } catch (Throwable $exception) {
            if ($disk !== null && $path !== null && $quarantinePath !== null) {
                Storage::disk($disk)->move($quarantinePath, $path);
            }

            throw $exception;
        }

        if ($disk !== null && $quarantinePath !== null && ! Storage::disk($disk)->delete($quarantinePath)) {
            report(new RuntimeException('An export quarantine file could not be removed.'));
        }
    }
}
