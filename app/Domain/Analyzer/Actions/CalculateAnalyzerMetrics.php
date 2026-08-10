<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Semantic\Actions\CalculateSemanticPerformance;
use App\Domain\Semantic\Actions\CalculateSemanticTopicProfile;
use App\Models\AnalyzerRun;

final readonly class CalculateAnalyzerMetrics
{
    public function __construct(
        private CalculateAnalyzerVideoProfile $videoProfile,
        private CalculateChannelBaseline $channelBaseline,
        private CalculateAnalyzerRelativePerformance $relativePerformance,
        private CalculateAnalyzerChannelBehavior $channelBehavior,
        private CalculateSemanticTopicProfile $semanticTopicProfile,
        private CalculateSemanticPerformance $semanticPerformance,
    ) {}

    public function handle(AnalyzerRun $run): void
    {
        if ($run->target_kind === 'video') {
            $this->videoProfile->handle($run);
        }

        $this->channelBaseline->handle($run);
        $this->relativePerformance->handle($run);
        $this->channelBehavior->handle($run);
        $this->semanticTopicProfile->handle($run);
        $this->semanticPerformance->handle($run);
    }
}
