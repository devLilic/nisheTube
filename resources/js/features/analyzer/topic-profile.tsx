import { AlertTriangle, BrainCircuit, Languages } from 'lucide-react';
import { StatePanel } from '@/components/data-state';
import { PartialDataBanner } from '@/components/partial-data-banner';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AnalyzerRun } from '@/types/analyzer';

function percent(value: number | null) {
    return value === null ? 'Unavailable' : `${value.toFixed(1)}%`;
}

function timestamp(value: string, timezone: string) {
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function hasMeaningfulTerm(label: string) {
    const terms = label.toLocaleLowerCase().trim().split(/\s+/u);

    return terms.length > 0 && !(terms.length === 2 && terms[0] === terms[1]);
}

function synonymKey(label: string) {
    return label
        .toLocaleLowerCase()
        .replaceAll('organisation', 'organization')
        .replaceAll('organising', 'organization')
        .replaceAll('organizing', 'organization')
        .trim();
}

function qualifiedLabels(
    labels: NonNullable<AnalyzerRun['topic_profile']>['topics'],
) {
    const grouped = new Map<string, (typeof labels)[number]>();

    for (const label of labels) {
        const frequency = label.evidence_video_ids?.length ?? 0;

        if (
            (label.confidence ?? 0) < 50 ||
            frequency < 2 ||
            !hasMeaningfulTerm(label.label)
        ) {
            continue;
        }

        const key = synonymKey(label.label);
        const current = grouped.get(key);

        if (!current || (label.confidence ?? 0) > (current.confidence ?? 0)) {
            grouped.set(key, label);
        }
    }

    return [...grouped.values()];
}

export function TopicProfile({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const profile = run.topic_profile;
    const topics = profile ? qualifiedLabels(profile.topics) : [];
    const pillars = profile ? qualifiedLabels(profile.content_pillars) : [];

    if (!profile && run.is_active) {
        return (
            <div aria-busy="true">
                <StatePanel
                    title="Detecting topic profile"
                    description="Stored video titles will be classified after the recent-video cohort is ready. No provider request is made for this inferred analysis."
                    icon={BrainCircuit}
                />
            </div>
        );
    }

    if (!profile || profile.status === 'insufficient') {
        return (
            <StatePanel
                title="Topic profile unavailable"
                description={
                    profile?.warnings[0] ??
                    'This attempt did not retain enough usable stored titles for inferred topic classification.'
                }
                icon={Languages}
            />
        );
    }

    if (profile.status === 'failed') {
        return (
            <StatePanel
                title="Topic profile could not be calculated"
                description="The Analyzer result remains available. Refreshing creates a new immutable attempt and retries inferred classification from that attempt's stored inputs."
                icon={AlertTriangle}
            />
        );
    }

    return (
        <section aria-labelledby="topic-profile-title" className="space-y-3">
            {profile.status === 'partial' && (
                <PartialDataBanner
                    title="Partial inferred classification"
                    description={
                        profile.warnings[0] ??
                        'The stored title cohort has limited semantic coverage.'
                    }
                />
            )}
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle id="topic-profile-title">
                                Topic Profile
                            </CardTitle>
                            <CardDescription className="mt-1">
                                Versioned classification inferred from stored
                                video titles. This is not a YouTube API fact or
                                an opportunity score.
                            </CardDescription>
                        </div>
                        <Badge variant="outline">Inferred</Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div className="rounded-xl border bg-muted/20 p-4">
                            <dt className="text-xs text-muted-foreground">
                                Detected niche
                            </dt>
                            <dd className="mt-2 font-semibold">
                                {profile.niche?.label ?? 'Unavailable'}
                            </dd>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Confidence{' '}
                                {percent(profile.niche?.confidence ?? null)}
                            </p>
                        </div>
                        <div className="rounded-xl border bg-muted/20 p-4">
                            <dt className="text-xs text-muted-foreground">
                                Detected subniche
                            </dt>
                            <dd className="mt-2 font-semibold">
                                {profile.subniche?.label ?? 'Unavailable'}
                            </dd>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Confidence{' '}
                                {percent(profile.subniche?.confidence ?? null)}
                            </p>
                        </div>
                        <div className="rounded-xl border bg-muted/20 p-4">
                            <dt className="text-xs text-muted-foreground">
                                Niche concentration
                            </dt>
                            <dd className="mt-2 font-semibold tabular-nums">
                                {percent(profile.concentration_score)}
                            </dd>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Share of leading repeated topic evidence
                            </p>
                        </div>
                        <div className="rounded-xl border bg-muted/20 p-4">
                            <dt className="text-xs text-muted-foreground">
                                Profile confidence
                            </dt>
                            <dd className="mt-2 font-semibold tabular-nums">
                                {percent(profile.confidence_score)}
                            </dd>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {profile.evidence_video_count} stored titles
                            </p>
                        </div>
                    </dl>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <div>
                            <h3 className="text-sm font-semibold">
                                Detected topics
                            </h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {topics.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No repeated topic passed the stored
                                        confidence and frequency guard.
                                    </p>
                                )}
                                {topics.map((topic) => (
                                    <Badge key={topic.key} variant="secondary">
                                        Topic: {topic.label} ·{' '}
                                        {percent(topic.confidence)} · n=
                                        {topic.evidence_video_ids?.length ?? 0}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold">
                                Content pillars
                            </h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {pillars.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No repeated title fragments passed the
                                        stored quality guard.
                                    </p>
                                )}
                                {pillars.map((pillar) => (
                                    <Badge key={pillar.key} variant="outline">
                                        Title fragment: {pillar.label} ·{' '}
                                        {percent(pillar.confidence)} · n=
                                        {pillar.evidence_video_ids?.length ?? 0}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    </div>

                    <p className="text-xs leading-5 text-muted-foreground">
                        Topic confidence applies to each repeated topic; profile
                        confidence applies to the whole title cohort; coverage
                        is shown as stored-title count. Synonym variants are
                        grouped only where supported. Language:{' '}
                        {profile.language} · Provider: {profile.provider} ·
                        Version: {profile.algorithm_version} · Calculated{' '}
                        {timestamp(profile.calculated_at, timezone)}
                    </p>
                </CardContent>
            </Card>
        </section>
    );
}
