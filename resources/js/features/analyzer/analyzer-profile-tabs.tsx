import {
    ChartNoAxesCombined,
    Clapperboard,
    Database,
    RadioTower,
} from 'lucide-react';
import { useId, useState } from 'react';
import { AnalyzerChannelCohort } from '@/features/analyzer/analyzer-channel-cohort';
import { AnalyzerCommentsSection } from '@/features/analyzer/analyzer-comments';
import { AnalyzerCurationPanel } from '@/features/analyzer/analyzer-curation';
import { AnalyzerDecisionSummary } from '@/features/analyzer/analyzer-decision-summary';
import { AnalyzerGrowthHistory } from '@/features/analyzer/analyzer-growth-history';
import { AnalyzerProfile } from '@/features/analyzer/analyzer-profile';
import { AnalyzerRawData } from '@/features/analyzer/analyzer-raw-data';
import { AnalyzerTranscriptSection } from '@/features/analyzer/analyzer-transcript';
import { AudienceSignalsSection } from '@/features/analyzer/audience-signals';
import { ThumbnailAnalysis } from '@/features/analyzer/thumbnail-analysis';
import { TopicPerformance } from '@/features/analyzer/topic-performance';
import { TopicProfile } from '@/features/analyzer/topic-profile';
import { TranscriptStructure } from '@/features/analyzer/transcript-structure';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import { cn } from '@/lib/utils';
import type { AnalyzerRun, LibraryContext } from '@/types';

type TabKey = 'summary' | 'content' | 'channel' | 'raw';

const tabs = [
    { key: 'summary' as const, label: 'Summary', icon: ChartNoAxesCombined },
    { key: 'content' as const, label: 'Content patterns', icon: Clapperboard },
    { key: 'channel' as const, label: 'Channel', icon: RadioTower },
    { key: 'raw' as const, label: 'Raw data', icon: Database },
];

export function AnalyzerProfileTabs({
    run,
    timezone,
    library,
    workspaces,
}: {
    run: AnalyzerRun;
    timezone: string;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
}) {
    const [activeTab, setActiveTab] = useState<TabKey>('summary');
    const id = useId();
    const selectAdjacentTab = (current: TabKey, direction: -1 | 1) => {
        const currentIndex = tabs.findIndex((tab) => tab.key === current);
        const next =
            tabs[(currentIndex + direction + tabs.length) % tabs.length];

        setActiveTab(next.key);
        window.requestAnimationFrame(() =>
            document.getElementById(`${id}-${next.key}-tab`)?.focus(),
        );
    };

    return (
        <section aria-label="Analyzer profile sections">
            <div
                className="flex gap-1 overflow-x-auto rounded-xl border bg-muted/35 p-1"
                role="tablist"
                aria-label="Analyzer profile sections"
            >
                {tabs.map((tab) => {
                    const Icon = tab.icon;
                    const selected = activeTab === tab.key;

                    return (
                        <button
                            key={tab.key}
                            id={`${id}-${tab.key}-tab`}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            aria-controls={`${id}-${tab.key}-panel`}
                            tabIndex={selected ? 0 : -1}
                            onClick={() => setActiveTab(tab.key)}
                            onKeyDown={(event) => {
                                if (event.key === 'ArrowRight') {
                                    event.preventDefault();
                                    selectAdjacentTab(tab.key, 1);
                                }

                                if (event.key === 'ArrowLeft') {
                                    event.preventDefault();
                                    selectAdjacentTab(tab.key, -1);
                                }
                            }}
                            className={cn(
                                'inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-3.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                selected
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-background/65 hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            <div
                id={`${id}-summary-panel`}
                role="tabpanel"
                aria-labelledby={`${id}-summary-tab`}
                hidden={activeTab !== 'summary'}
                className="mt-5 space-y-5 [&_[data-slot=card-content]]:px-5 [&_[data-slot=card-header]]:px-5 [&_[data-slot=card]]:gap-5 [&_[data-slot=card]]:py-5"
            >
                <AnalyzerDecisionSummary run={run} />
            </div>

            <div
                id={`${id}-content-panel`}
                role="tabpanel"
                aria-labelledby={`${id}-content-tab`}
                hidden={activeTab !== 'content'}
                className="mt-5 space-y-5 [&_[data-slot=card-content]]:px-5 [&_[data-slot=card-header]]:px-5 [&_[data-slot=card]]:gap-5 [&_[data-slot=card]]:py-5"
            >
                <AnalyzerProfile
                    run={run}
                    timezone={timezone}
                    section="video"
                />
                <TopicProfile run={run} timezone={timezone} />
                <TopicPerformance run={run} timezone={timezone} />
                <ThumbnailAnalysis run={run} timezone={timezone} />
                <AnalyzerCommentsSection run={run} timezone={timezone} />
                <AudienceSignalsSection run={run} timezone={timezone} />
                <AnalyzerTranscriptSection run={run} timezone={timezone} />
                <TranscriptStructure run={run} timezone={timezone} />
            </div>

            <div
                id={`${id}-channel-panel`}
                role="tabpanel"
                aria-labelledby={`${id}-channel-tab`}
                hidden={activeTab !== 'channel'}
                className="mt-5 space-y-5 [&_[data-slot=card-content]]:px-5 [&_[data-slot=card-header]]:px-5 [&_[data-slot=card]]:gap-5 [&_[data-slot=card]]:py-5"
            >
                <AnalyzerProfile
                    run={run}
                    timezone={timezone}
                    section="channel"
                />
                {!run.channel && (
                    <TabEmptyState
                        title="Channel profile is not ready"
                        description="Channel identity, cohort, and behavior blocks appear here after the persisted analysis reaches that stage."
                    />
                )}
                {run.channel && (
                    <AnalyzerChannelCohort run={run} timezone={timezone} />
                )}
                {run.channel && (
                    <AnalyzerGrowthHistory run={run} timezone={timezone} />
                )}
            </div>

            <div
                id={`${id}-raw-panel`}
                role="tabpanel"
                aria-labelledby={`${id}-raw-tab`}
                hidden={activeTab !== 'raw'}
                className="mt-5 space-y-5 [&_[data-slot=card-content]]:px-5 [&_[data-slot=card-header]]:px-5 [&_[data-slot=card]]:gap-5 [&_[data-slot=card]]:py-5"
            >
                <AnalyzerRawData run={run} />
                <AnalyzerCurationPanel
                    run={run}
                    library={library}
                    workspaces={workspaces}
                    hideHandoffs
                />
            </div>
        </section>
    );
}

function TabEmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-xl border border-dashed bg-muted/15 px-5 py-8 text-center">
            <p className="text-sm font-medium">{title}</p>
            <p className="mx-auto mt-1 max-w-xl text-xs leading-5 text-muted-foreground">
                {description}
            </p>
        </div>
    );
}
