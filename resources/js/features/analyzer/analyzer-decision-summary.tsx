import { CheckCircle2, CircleAlert, Gauge, Layers3 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AnalyzerRun } from '@/types/analyzer';

function number(value: number | null | undefined) {
    return value === null || value === undefined
        ? 'Unavailable'
        : new Intl.NumberFormat('en', { maximumFractionDigits: 2 }).format(
              value,
          );
}

export function AnalyzerDecisionSummary({ run }: { run: AnalyzerRun }) {
    const topic = run.topic_profile?.topics[0];
    const videoRatio = run.metrics?.channel_median_ratio;
    const coverage = run.channel_metrics?.coverage_percent;
    const hasEvidence = Boolean(run.channel || run.video);

    if (!hasEvidence && run.is_active) {
        return (
            <Card aria-busy="true">
                <CardHeader>
                    <CardTitle>Decision summary is loading</CardTitle>
                    <CardDescription>
                        The summary will use only the observations persisted for
                        this analysis attempt.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    if (!hasEvidence) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Decision summary unavailable</CardTitle>
                    <CardDescription>
                        No stored video or channel observation is available for
                        this attempt.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    return (
        <section aria-labelledby="analyzer-summary-title" className="space-y-4">
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle id="analyzer-summary-title">
                                Summary
                            </CardTitle>
                            <CardDescription className="mt-1 max-w-3xl">
                                A plain-language reading of this saved
                                observation. It is not a prediction,
                                recommendation, or YouTube search-volume
                                measure.
                            </CardDescription>
                        </div>
                        <Badge variant="outline">Stored evidence only</Badge>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-3">
                    <Conclusion
                        icon={Gauge}
                        title="Current video position"
                        value={
                            videoRatio === null || videoRatio === undefined
                                ? 'Not comparable yet'
                                : videoRatio >= 1
                                  ? 'At or above this channel’s typical result'
                                  : 'Below this channel’s typical result'
                        }
                        detail={
                            videoRatio === null || videoRatio === undefined
                                ? 'A channel-relative median ratio was not available from the stored cohort.'
                                : `${number(videoRatio)}× the stored channel median. Exact calculation is available in Raw data.`
                        }
                    />
                    <Conclusion
                        icon={Layers3}
                        title="Repeated topic evidence"
                        value={topic ? topic.label : 'No qualified topic'}
                        detail={
                            topic
                                ? `${number(topic.confidence)}% topic confidence from ${topic.evidence_video_ids?.length ?? 0} stored title${(topic.evidence_video_ids?.length ?? 0) === 1 ? '' : 's'}.`
                                : 'A topic appears only when stored title evidence passes the quality guard.'
                        }
                    />
                    <Conclusion
                        icon={
                            coverage !== null &&
                            coverage !== undefined &&
                            coverage >= 75
                                ? CheckCircle2
                                : CircleAlert
                        }
                        title="Cohort coverage"
                        value={
                            coverage === null || coverage === undefined
                                ? 'Unavailable'
                                : `${number(coverage)}% collected`
                        }
                        detail={
                            coverage === null || coverage === undefined
                                ? 'The channel cohort has not produced a coverage value.'
                                : 'Coverage describes the stored recent-video cohort, not the channel’s complete history.'
                        }
                    />
                </CardContent>
            </Card>
        </section>
    );
}

function Conclusion({
    icon: Icon,
    title,
    value,
    detail,
}: {
    icon: typeof Gauge;
    title: string;
    value: string;
    detail: string;
}) {
    return (
        <div className="rounded-xl border bg-muted/15 p-4">
            <div className="flex items-center gap-2 text-sm font-medium">
                <Icon className="size-4 text-primary" aria-hidden="true" />
                {title}
            </div>
            <p className="mt-3 font-semibold break-words">{value}</p>
            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                {detail}
            </p>
        </div>
    );
}
