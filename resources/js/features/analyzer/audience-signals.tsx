import { router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    EyeOff,
    MessagesSquare,
    RotateCcw,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/formatters';
import type { AnalyzerAudienceSignal, AnalyzerRun } from '@/types';

const KIND_LABELS: Record<AnalyzerAudienceSignal['kind'], string> = {
    repeated_question: 'Repeated questions',
    topic: 'Topics',
    entity: 'Entities',
    suggestion: 'Suggestions',
    complaint: 'Complaints',
    confusion_point: 'Confusion points',
};

export function AudienceSignalsSection({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const profile = run.comments?.audience_signals;
    const audienceForm = useForm({});
    const [pendingWord, setPendingWord] = useState<string | null>(null);
    const [restoringId, setRestoringId] = useState<string | null>(null);

    if (!profile) {
        const canAnalyze =
            (run.comments?.status === 'completed' ||
                run.comments?.status === 'partial') &&
            (run.comments?.items.length ?? 0) > 0;

        if (!canAnalyze) {
            return null;
        }

        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MessagesSquare className="size-5" /> Audience Signals
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Infer repeated patterns from the stored comment sample.
                        This local calculation makes no YouTube request and uses
                        no quota.
                    </p>
                </CardHeader>
                <CardContent>
                    <button
                        type="button"
                        disabled={audienceForm.processing}
                        aria-busy={audienceForm.processing}
                        className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        onClick={() =>
                            audienceForm.post(
                                `/analyzer/runs/${run.public_id}/audience-signals`,
                            )
                        }
                    >
                        {audienceForm.processing
                            ? 'Analyzing audience signals…'
                            : 'Analyze audience signals'}
                    </button>
                </CardContent>
            </Card>
        );
    }

    const grouped = Object.entries(KIND_LABELS)
        .map(([kind, label]) => ({
            kind: kind as AnalyzerAudienceSignal['kind'],
            label,
            signals: profile.signals.filter((signal) => signal.kind === kind),
        }))
        .filter((group) => group.signals.length > 0);

    const excludeWord = (word: string) => {
        router.post(
            `/analyzer/runs/${run.public_id}/audience-signal-exclusions`,
            { word },
            {
                preserveScroll: true,
                onStart: () => setPendingWord(word),
                onFinish: () => setPendingWord(null),
            },
        );
    };

    const restoreWord = (publicId: string) => {
        router.delete(
            `/analyzer/runs/${run.public_id}/audience-signal-exclusions/${publicId}`,
            {
                preserveScroll: true,
                onStart: () => setRestoringId(publicId),
                onFinish: () => setRestoringId(null),
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MessagesSquare className="size-5" /> Audience Signals
                </CardTitle>
                <p className="text-sm text-muted-foreground">
                    Inferred patterns from this stored top-level comment sample,
                    not authoritative sentiment or a claim about the full
                    audience.
                </p>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline">Inferred analysis</Badge>
                    <Badge variant="outline">Language {profile.language}</Badge>
                    <Badge variant="outline">
                        {profile.usable_comment_count}/{profile.comment_count}{' '}
                        usable comments
                    </Badge>
                    <Badge variant="outline">
                        Confidence{' '}
                        {profile.confidence_score === null
                            ? 'unavailable'
                            : `${Math.round(profile.confidence_score)}%`}
                    </Badge>
                    {profile.hidden_signal_count > 0 && (
                        <Badge variant="secondary">
                            {profile.hidden_signal_count} hidden{' '}
                            {profile.hidden_signal_count === 1
                                ? 'signal'
                                : 'signals'}
                        </Badge>
                    )}
                </div>

                {profile.excluded_words.length > 0 && (
                    <section
                        aria-labelledby="excluded-audience-words"
                        className="rounded-xl border bg-muted/25 p-4"
                    >
                        <h3
                            id="excluded-audience-words"
                            className="font-medium"
                        >
                            Hidden single words
                        </h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Only an exact one-word signal is hidden. Longer
                            phrases containing the word remain visible.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {profile.excluded_words.map((exclusion) => (
                                <Button
                                    key={exclusion.public_id}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={restoringId !== null}
                                    aria-busy={
                                        restoringId === exclusion.public_id
                                    }
                                    onClick={() =>
                                        restoreWord(exclusion.public_id)
                                    }
                                >
                                    <RotateCcw /> Restore {exclusion.word}
                                </Button>
                            ))}
                        </div>
                    </section>
                )}

                {profile.warnings.map((warning) => (
                    <Alert key={warning}>
                        <AlertTriangle />
                        <AlertTitle>Audience Signal limitation</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ))}

                {profile.status === 'unsafe' && (
                    <Alert variant="destructive">
                        <ShieldAlert />
                        <AlertTitle>Unsafe output withheld</AlertTitle>
                        <AlertDescription>
                            No signal labels are shown because the stored sample
                            could expose unsafe or identifying content.
                        </AlertDescription>
                    </Alert>
                )}

                {profile.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Audience Signals failed</AlertTitle>
                        <AlertDescription>
                            The inferred layer could not be calculated. Stored
                            comments remain available and unchanged.
                        </AlertDescription>
                    </Alert>
                )}

                {profile.status === 'insufficient' && (
                    <Alert>
                        <MessagesSquare />
                        <AlertTitle>Not enough repeated evidence</AlertTitle>
                        <AlertDescription>
                            The stored sample is too sparse to support a
                            repeated audience pattern. No zero-confidence claim
                            is shown.
                        </AlertDescription>
                    </Alert>
                )}

                {grouped.length === 0 && profile.hidden_signal_count > 0 && (
                    <Alert>
                        <EyeOff />
                        <AlertTitle>All matching signals are hidden</AlertTitle>
                        <AlertDescription>
                            Restore a word above to show its exact single-word
                            signal again.
                        </AlertDescription>
                    </Alert>
                )}

                {grouped.map((group) => (
                    <section
                        key={group.kind}
                        aria-labelledby={`audience-${group.kind}`}
                    >
                        <h3
                            id={`audience-${group.kind}`}
                            className="mb-2 font-medium"
                        >
                            {group.label}
                        </h3>
                        <div className="grid gap-3 lg:grid-cols-2">
                            {group.signals.map((signal) => (
                                <details
                                    key={`${signal.kind}-${signal.label}`}
                                    className="rounded-lg border p-4"
                                >
                                    <summary className="cursor-pointer list-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <span className="font-medium">
                                                {signal.label}
                                            </span>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="secondary">
                                                    {signal.comment_count}{' '}
                                                    source comments
                                                </Badge>
                                                {signal.can_exclude && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        disabled={
                                                            pendingWord !== null
                                                        }
                                                        aria-busy={
                                                            pendingWord ===
                                                            signal.label
                                                        }
                                                        title="Hide only this exact single-word signal; phrases remain visible"
                                                        onClick={(event) => {
                                                            event.preventDefault();
                                                            event.stopPropagation();
                                                            excludeWord(
                                                                signal.label,
                                                            );
                                                        }}
                                                    >
                                                        <EyeOff /> Hide word
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {signal.occurrence_count} observed
                                            occurrence(s) ·{' '}
                                            {Math.round(signal.confidence ?? 0)}
                                            % inferred confidence
                                        </p>
                                    </summary>
                                    <ol
                                        className="mt-3 space-y-2 border-t pt-3"
                                        aria-label={`Evidence for ${signal.label}`}
                                    >
                                        {signal.evidence.map((evidence) => (
                                            <li
                                                key={evidence.comment_id}
                                                className="text-sm"
                                            >
                                                <q className="break-words">
                                                    {evidence.text}
                                                </q>
                                                {evidence.published_at && (
                                                    <span className="mt-1 block text-xs text-muted-foreground">
                                                        Published{' '}
                                                        {formatDate(
                                                            evidence.published_at,
                                                            timezone,
                                                        )}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                </details>
                            ))}
                        </div>
                    </section>
                ))}

                <p className="text-xs text-muted-foreground">
                    Provider {profile.provider} · Version{' '}
                    {profile.algorithm_version} · Calculated{' '}
                    {formatDate(profile.calculated_at, timezone)}
                </p>
            </CardContent>
        </Card>
    );
}
