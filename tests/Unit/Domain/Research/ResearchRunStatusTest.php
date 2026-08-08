<?php

namespace Tests\Unit\Domain\Research;

use App\Domain\Research\Enums\ResearchRunStatus;
use PHPUnit\Framework\TestCase;

class ResearchRunStatusTest extends TestCase
{
    public function test_transition_matrix_allows_only_documented_lifecycle_edges_and_idempotency(): void
    {
        $allowedTransitions = [
            ResearchRunStatus::Draft->value => [ResearchRunStatus::Draft, ResearchRunStatus::Queued],
            ResearchRunStatus::Queued->value => [
                ResearchRunStatus::Queued,
                ResearchRunStatus::Searching,
                ResearchRunStatus::Failed,
            ],
            ResearchRunStatus::Searching->value => [
                ResearchRunStatus::Searching,
                ResearchRunStatus::Enriching,
                ResearchRunStatus::Failed,
            ],
            ResearchRunStatus::Enriching->value => [
                ResearchRunStatus::Enriching,
                ResearchRunStatus::Scoring,
                ResearchRunStatus::Failed,
            ],
            ResearchRunStatus::Scoring->value => [
                ResearchRunStatus::Scoring,
                ResearchRunStatus::Completed,
                ResearchRunStatus::Failed,
            ],
            ResearchRunStatus::Completed->value => [ResearchRunStatus::Completed],
            ResearchRunStatus::Failed->value => [ResearchRunStatus::Failed],
        ];

        foreach (ResearchRunStatus::cases() as $current) {
            foreach (ResearchRunStatus::cases() as $next) {
                $this->assertSame(
                    in_array($next, $allowedTransitions[$current->value], true),
                    $current->canTransitionTo($next),
                    "Unexpected {$current->value} -> {$next->value} transition result.",
                );
            }
        }

        $this->assertFalse(ResearchRunStatus::Draft->isTerminal());
        $this->assertFalse(ResearchRunStatus::Scoring->isTerminal());
        $this->assertTrue(ResearchRunStatus::Completed->isTerminal());
        $this->assertTrue(ResearchRunStatus::Failed->isTerminal());
    }
}
