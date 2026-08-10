<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Models\AnalyzerRun;
use Throwable;

final readonly class FailAnalyzerRun
{
    public function __construct(private TransitionAnalyzerRun $transition) {}

    public function handle(int $runId, Throwable $exception): ?AnalyzerRun
    {
        $run = AnalyzerRun::query()->find($runId);

        if ($run === null || $run->status->isTerminal()) {
            return $run;
        }

        $code = $exception instanceof YouTubeProviderException
            ? $exception->providerCode->value
            : 'analyzer_collection_failed';
        $message = $exception instanceof YouTubeProviderException
            ? $exception->providerCode->safeMessage()
            : 'The Analyzer collection could not be completed. Retry the saved attempt or review the local logs.';

        return $this->transition->handle($run, AnalyzerRunStatus::Failed, $code, $message);
    }
}
