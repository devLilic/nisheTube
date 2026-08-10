import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Braces,
    ChevronDown,
    LoaderCircle,
    Sparkles,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/formatters';
import type { AnalyzerRun, AnalyzerTranscriptStructureInsight } from '@/types';

const groupLabels: Record<AnalyzerTranscriptStructureInsight['kind'], string> =
    {
        summary: 'Summary',
        topic: 'Topics',
        entity: 'Entities',
        hook: 'Hook',
        section: 'Sections',
        cta: 'Calls to action',
        question: 'Questions',
        script_structure: 'Script structure',
    };

const groupOrder = [
    'summary',
    'hook',
    'script_structure',
    'section',
    'topic',
    'entity',
    'cta',
    'question',
] as const;

export function TranscriptStructure({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const document = run.transcript?.document;
    const analysis = document?.analysis;
    const form = useForm({});

    if (!document) {
        return null;
    }

    const calculate = () =>
        form.post(
            `/analyzer/runs/${run.public_id}/transcripts/${document.public_id}/structure`,
            { preserveScroll: true },
        );

    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Sparkles className="size-5" /> Transcript structure
                    </CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Inferred research notes generated from the current
                        stored revision. Original transcript text remains in the
                        separate Transcript section above.
                    </p>
                </div>
                {!analysis && (
                    <Button
                        type="button"
                        onClick={calculate}
                        disabled={form.processing}
                        aria-busy={form.processing}
                    >
                        {form.processing ? (
                            <>
                                <LoaderCircle className="animate-spin" />
                                Analyzing structure…
                            </>
                        ) : (
                            <>
                                <Braces /> Analyze transcript structure
                            </>
                        )}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-5">
                {!analysis && (
                    <Alert>
                        <Sparkles />
                        <AlertTitle>
                            Transcript structure not analyzed
                        </AlertTitle>
                        <AlertDescription>
                            Run the local deterministic analysis to infer a
                            summary, topics, entities, hook, sections, calls to
                            action, questions, and script structure. This uses
                            no YouTube quota.
                        </AlertDescription>
                    </Alert>
                )}

                {analysis?.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Structure analysis failed</AlertTitle>
                        <AlertDescription>
                            The inferred output could not be produced. The
                            original transcript revision is unchanged and
                            remains searchable.
                        </AlertDescription>
                    </Alert>
                )}

                {analysis?.status === 'insufficient' && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>
                            Insufficient transcript evidence
                        </AlertTitle>
                        <AlertDescription>
                            This revision does not contain enough readable text
                            for a defensible structure profile.
                        </AlertDescription>
                    </Alert>
                )}

                {analysis && analysis.status !== 'failed' && (
                    <>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">Detected analysis</Badge>
                            <Badge variant="outline" className="capitalize">
                                {analysis.status}
                            </Badge>
                            <Badge variant="outline">
                                Language {analysis.language}
                            </Badge>
                            <Badge variant="outline">
                                Confidence{' '}
                                {analysis.confidence_score === null
                                    ? 'unavailable'
                                    : `${Math.round(analysis.confidence_score)}%`}
                            </Badge>
                            <Badge variant="outline">
                                {analysis.evidence_count} evidence-linked
                                item(s)
                            </Badge>
                        </div>

                        {analysis.warnings.map((warning) => (
                            <Alert key={warning}>
                                <AlertTriangle />
                                <AlertTitle>Partial inferred output</AlertTitle>
                                <AlertDescription>{warning}</AlertDescription>
                            </Alert>
                        ))}

                        {groupOrder.map((kind) => {
                            const insights = analysis.insights.filter(
                                (insight) => insight.kind === kind,
                            );

                            if (insights.length === 0) {
                                return null;
                            }

                            return (
                                <section key={kind} className="space-y-2">
                                    <h3 className="text-sm font-semibold">
                                        {groupLabels[kind]}
                                    </h3>
                                    <div className="grid gap-3 lg:grid-cols-2">
                                        {insights.map((insight) => (
                                            <InsightCard
                                                key={`${kind}-${insight.position}`}
                                                insight={insight}
                                                providerVideoId={
                                                    run.target_provider_id
                                                }
                                            />
                                        ))}
                                    </div>
                                </section>
                            );
                        })}
                    </>
                )}

                {analysis && (
                    <p className="text-xs text-muted-foreground">
                        Inferred provenance · Provider {analysis.provider} ·
                        Version {analysis.algorithm_version} ·{' '}
                        {analysis.word_count.toLocaleString()} source words ·
                        Calculated{' '}
                        {formatDate(analysis.calculated_at, timezone)}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function InsightCard({
    insight,
    providerVideoId,
}: {
    insight: AnalyzerTranscriptStructureInsight;
    providerVideoId: string;
}) {
    const seconds =
        insight.start_ms === null ? null : Math.floor(insight.start_ms / 1000);

    return (
        <article className="rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-medium break-words">{insight.label}</p>
                    {insight.detail && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {insight.detail}
                        </p>
                    )}
                </div>
                <Badge variant="secondary">
                    {Math.round(insight.confidence)}% confidence
                </Badge>
            </div>
            <details className="mt-3 text-sm">
                <summary className="flex cursor-pointer list-none items-center gap-1 font-medium text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                    <ChevronDown className="size-4" /> View original evidence
                </summary>
                <blockquote className="mt-2 border-l-2 pl-3 break-words text-muted-foreground">
                    {insight.evidence_text}
                </blockquote>
                <p className="mt-2 text-xs text-muted-foreground">
                    Character offsets {insight.start_offset}–
                    {insight.end_offset}
                    {seconds !== null && (
                        <>
                            {' · '}
                            <a
                                href={`https://www.youtube.com/watch?v=${providerVideoId}&t=${seconds}s`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                Open timestamped evidence
                            </a>
                        </>
                    )}
                </p>
            </details>
        </article>
    );
}
