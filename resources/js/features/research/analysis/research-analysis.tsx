import { BarChart3, LoaderCircle } from 'lucide-react';
import { StatePanel } from '@/components/data-state';
import { PartialDataBanner } from '@/components/partial-data-banner';
import type {
    LibraryContext,
    ResearchAnalysis,
    ResearchRunStatus,
} from '@/types';
import { AnalysisCharts } from './analysis-charts';
import { AnalysisOverview } from './analysis-overview';
import { AnalysisTables } from './analysis-tables';

export function ResearchAnalysisSection({
    analysis,
    status,
    timezone,
    library,
}: {
    analysis: ResearchAnalysis | undefined;
    status: ResearchRunStatus;
    timezone: string;
    library: LibraryContext;
}) {
    if (!analysis || analysis.videos.length === 0) {
        const active = !['completed', 'failed'].includes(status);

        return (
            <section aria-label="Run analysis">
                <StatePanel
                    title={
                        active
                            ? 'Analysis is being prepared'
                            : 'No enriched metrics available'
                    }
                    description={
                        active
                            ? 'The saved candidate preview remains available below. Snapshot metrics will appear here as enrichment finishes.'
                            : 'This run did not produce enough enriched snapshot data for aggregate analysis.'
                    }
                    icon={active ? LoaderCircle : BarChart3}
                />
            </section>
        );
    }

    return (
        <div className="space-y-6">
            {analysis.summary.hidden_subscriber_channels > 0 && (
                <PartialDataBanner
                    title="Some subscriber counts are hidden"
                    description={`${analysis.summary.hidden_subscriber_channels.toLocaleString('en-US')} captured channels hide subscriber counts. Subscriber and reach aggregates exclude those missing values.`}
                />
            )}
            {analysis.summary.missing_video_metric_count > 0 && (
                <PartialDataBanner
                    title="Some video metrics are unavailable"
                    description={`${analysis.summary.missing_video_metric_count.toLocaleString('en-US')} enriched videos have at least one missing metric. Available values remain visible and missing values are not treated as zero.`}
                />
            )}
            <AnalysisOverview summary={analysis.summary} timezone={timezone} />
            <AnalysisCharts
                videos={analysis.videos}
                channels={analysis.channels}
            />
            <AnalysisTables
                videos={analysis.videos}
                channels={analysis.channels}
                timezone={timezone}
                library={library}
            />
        </div>
    );
}
