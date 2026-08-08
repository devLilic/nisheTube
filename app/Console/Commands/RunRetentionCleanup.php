<?php

namespace App\Console\Commands;

use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Enums\CleanupMode;
use App\Models\User;
use Illuminate\Console\Command;

final class RunRetentionCleanup extends Command
{
    protected $signature = 'retention:cleanup {--dry-run : Record eligibility previews without deleting data}';

    protected $description = 'Queue six-month snapshot retention cleanup for every local user';

    public function handle(CreateCleanupRun $cleanup): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;

        User::query()->orderBy('id')->eachById(function (User $user) use ($cleanup, $dryRun, &$count): void {
            $cleanup->handle($user, CleanupMode::Scheduled, $dryRun);
            $count++;
        });

        $verb = $dryRun ? 'previewed' : 'queued';
        $this->info("Retention cleanup {$verb} for {$count} user(s).");

        return self::SUCCESS;
    }
}
