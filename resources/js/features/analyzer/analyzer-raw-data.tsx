import { Database } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AnalyzerRun } from '@/types/analyzer';

const exact = new Intl.NumberFormat('en', { maximumFractionDigits: 6 });
const value = (input: number | null | undefined) =>
    input === null || input === undefined ? 'Unavailable' : exact.format(input);

export function AnalyzerRawData({ run }: { run: AnalyzerRun }) {
    return (
        <section aria-labelledby="raw-data-title" className="space-y-4">
            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <Database
                            className="mt-0.5 size-5 text-primary"
                            aria-hidden="true"
                        />
                        <div>
                            <CardTitle id="raw-data-title">Raw data</CardTitle>
                            <CardDescription className="mt-1">
                                Exact stored values, formula context,
                                provenance, and algorithm versions. Values are
                                not recalculated on this page.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-5 lg:grid-cols-2">
                    <DataBlock
                        title="Anchor observation"
                        rows={[
                            ['Video views', value(run.video?.view_count)],
                            [
                                'Channel subscribers',
                                run.channel?.subscriber_count_hidden
                                    ? 'Hidden by channel'
                                    : value(run.channel?.subscriber_count),
                            ],
                            [
                                'Observed at',
                                run.video?.observed_at ??
                                    run.channel?.observed_at ??
                                    'Unavailable',
                            ],
                            [
                                'Source',
                                run.video?.source_mode ??
                                    run.channel?.source_mode ??
                                    'Unavailable',
                            ],
                        ]}
                    />
                    <DataBlock
                        title="Calculated metrics"
                        rows={[
                            [
                                'Lifetime views/day',
                                value(run.metrics?.lifetime_views_per_day),
                            ],
                            [
                                'Channel-median ratio',
                                value(run.metrics?.channel_median_ratio),
                            ],
                            [
                                'Public engagement rate (%)',
                                value(
                                    run.metrics?.public_engagement_rate_percent,
                                ),
                            ],
                            [
                                'Formula version',
                                run.metrics?.calculation_version ??
                                    'Unavailable',
                            ],
                        ]}
                    />
                    <DataBlock
                        title="Formulas and cohort"
                        rows={[
                            [
                                'Lifetime views/day formula',
                                'Observed lifetime views ÷ age in days',
                            ],
                            [
                                'Channel-median ratio formula',
                                'Anchor views ÷ stored recent-cohort median views',
                            ],
                            [
                                'Cohort coverage (%)',
                                value(run.channel_metrics?.coverage_percent),
                            ],
                            [
                                'Relative threshold version',
                                run.relative_context?.threshold_version ??
                                    'Unavailable',
                            ],
                        ]}
                    />
                    <DataBlock
                        title="Semantic provenance"
                        rows={[
                            [
                                'Topic profile version',
                                run.topic_profile?.algorithm_version ??
                                    'Unavailable',
                            ],
                            [
                                'Topic profile confidence (%)',
                                value(run.topic_profile?.confidence_score),
                            ],
                            [
                                'Topic performance version',
                                run.topic_performance?.calculation_version ??
                                    'Unavailable',
                            ],
                            [
                                'Title-pattern version',
                                run.topic_performance?.title_pattern_version ??
                                    'Unavailable',
                            ],
                        ]}
                    />
                </CardContent>
            </Card>
        </section>
    );
}

function DataBlock({
    title,
    rows,
}: {
    title: string;
    rows: Array<[string, string]>;
}) {
    return (
        <div>
            <h3 className="text-sm font-semibold">{title}</h3>
            <dl className="mt-2 divide-y rounded-xl border text-sm">
                {rows.map(([label, entry]) => (
                    <div
                        key={label}
                        className="grid gap-1 px-3 py-2.5 sm:grid-cols-[minmax(10rem,.8fr)_1fr]"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="font-medium break-words tabular-nums">
                            {entry}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
