import { Form, Link } from '@inertiajs/react';
import {
    Bookmark,
    CheckCircle2,
    ExternalLink,
    EyeOff,
    Filter,
    SearchCheck,
    ScanSearch,
    Sparkles,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfidenceBadge } from '@/components/confidence-badge';
import { StatePanel } from '@/components/data-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { WorkspaceHandoff } from '@/features/integration/workspace-handoff';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import { FavoriteToggle } from '@/features/library/favorite-toggle';
import type {
    LibraryContext,
    NicheCandidate,
    NicheCandidateStatus,
} from '@/types';

function confidenceLabel(score: number) {
    if (score >= 80) {
        return 'High confidence';
    }

    if (score >= 60) {
        return 'Good confidence';
    }

    if (score >= 40) {
        return 'Moderate confidence';
    }

    return 'Low confidence';
}

function statusLabel(status: NicheCandidateStatus) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

export function CandidateList({
    candidates,
    validationBlocked,
    library,
    workspaces,
    discoveryRunPublicId,
}: {
    candidates: NicheCandidate[];
    validationBlocked: boolean;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
    discoveryRunPublicId: string;
}) {
    const [status, setStatus] = useState<NicheCandidateStatus | 'all'>('all');
    const [minimumScore, setMinimumScore] = useState(0);
    const [minimumConfidence, setMinimumConfidence] = useState(0);
    const [validationDepth, setValidationDepth] = useState(50);
    const filtered = useMemo(
        () =>
            candidates.filter(
                (candidate) =>
                    (status === 'all' || candidate.status === status) &&
                    (candidate.overall_score ?? 0) >= minimumScore &&
                    (candidate.confidence_score ?? 0) >= minimumConfidence,
            ),
        [candidates, minimumConfidence, minimumScore, status],
    );

    if (candidates.length === 0) {
        return (
            <StatePanel
                title="No breakout candidates found"
                description="The stored samples did not contain a strong recurring breakout theme. Try broader samples, a different market, or more seed runs."
                icon={Sparkles}
            />
        );
    }

    return (
        <div className="space-y-5">
            <Card className="py-4 shadow-none">
                <CardContent className="grid gap-4 px-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2">
                        <label
                            htmlFor="candidate-status"
                            className="text-xs font-medium"
                        >
                            <Filter className="mr-1 inline size-3" /> Status
                        </label>
                        <select
                            id="candidate-status"
                            value={status}
                            onChange={(event) =>
                                setStatus(
                                    event.target.value as
                                        NicheCandidateStatus | 'all',
                                )
                            }
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="all">All statuses</option>
                            <option value="new">New</option>
                            <option value="saved">Saved</option>
                            <option value="dismissed">Dismissed</option>
                            <option value="validated">Validated</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <label
                            htmlFor="minimum-score"
                            className="text-xs font-medium"
                        >
                            Minimum score
                        </label>
                        <select
                            id="minimum-score"
                            value={minimumScore}
                            onChange={(event) =>
                                setMinimumScore(Number(event.target.value))
                            }
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value={0}>Any score</option>
                            <option value={40}>40+</option>
                            <option value={60}>60+</option>
                            <option value={80}>80+</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <label
                            htmlFor="minimum-confidence"
                            className="text-xs font-medium"
                        >
                            Minimum confidence
                        </label>
                        <select
                            id="minimum-confidence"
                            value={minimumConfidence}
                            onChange={(event) =>
                                setMinimumConfidence(Number(event.target.value))
                            }
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value={0}>Any confidence</option>
                            <option value={40}>40+</option>
                            <option value={60}>60+</option>
                            <option value={80}>80+</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <label
                            htmlFor="validation-depth"
                            className="text-xs font-medium"
                        >
                            Validation depth
                        </label>
                        <select
                            id="validation-depth"
                            value={validationDepth}
                            onChange={(event) =>
                                setValidationDepth(Number(event.target.value))
                            }
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value={25}>25 results · 1 call</option>
                            <option value={50}>50 results · 1 call</option>
                            <option value={100}>100 results · 2 calls</option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            {validationBlocked && (
                <Alert className="border-warning/40 bg-warning/10">
                    <SearchCheck aria-hidden="true" />
                    <AlertTitle>
                        Validation is paused by the local quota estimate
                    </AlertTitle>
                    <AlertDescription>
                        Candidate evidence remains available. Wait for the
                        Pacific Time reset or check Google Cloud Console before
                        starting validation searches.
                    </AlertDescription>
                </Alert>
            )}

            {filtered.length === 0 ? (
                <StatePanel
                    title="No candidates match these filters"
                    description="Lower the score or confidence threshold, or include another candidate status."
                    icon={Filter}
                />
            ) : (
                <div className="grid gap-5 lg:grid-cols-2">
                    {filtered.map((candidate) => {
                        const score = candidate.overall_score ?? 0;
                        const confidence = candidate.confidence_score ?? 0;
                        const videoIds = candidate.evidence.video_ids ?? [];
                        const analyzerRunIds =
                            candidate.evidence.analyzer_run_ids ?? [];
                        const returnTo = `/discover/runs/${discoveryRunPublicId}`;

                        return (
                            <Card key={candidate.public_id} className="min-w-0">
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <CardTitle className="text-lg break-words">
                                                {candidate.phrase}
                                            </CardTitle>
                                            <CardDescription className="mt-2 leading-6">
                                                {candidate.summary}
                                            </CardDescription>
                                        </div>
                                        <Badge variant="outline">
                                            {statusLabel(candidate.status)}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge className="px-2.5 py-1 tabular-nums">
                                            Discovery evidence{' '}
                                            {score.toFixed(1)}
                                        </Badge>
                                        <ConfidenceBadge
                                            score={confidence}
                                            label={confidenceLabel(confidence)}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        This evidence rank is not an opportunity
                                        score. Run Validation Search before
                                        treating the candidate as validated.
                                    </p>

                                    <div>
                                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Observed evidence
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Badge variant="secondary">
                                                {candidate.evidence
                                                    .source_video_count ??
                                                    videoIds.length}{' '}
                                                breakout videos
                                            </Badge>
                                            <Badge variant="secondary">
                                                {candidate.evidence
                                                    .seed_count ?? 0}{' '}
                                                seeds
                                            </Badge>
                                            {(
                                                candidate.evidence
                                                    .seed_queries ?? []
                                            ).map((seed) => (
                                                <Badge
                                                    key={seed}
                                                    variant="outline"
                                                    className="max-w-full truncate"
                                                >
                                                    {seed}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>

                                    {(candidate.evidence.inferred_topics ?? [])
                                        .length > 0 && (
                                        <div>
                                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                Inferred Analyzer topics
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {(
                                                    candidate.evidence
                                                        .inferred_topics ?? []
                                                ).map((topic) => (
                                                    <Badge
                                                        key={topic}
                                                        variant="outline"
                                                    >
                                                        {topic}
                                                    </Badge>
                                                ))}
                                            </div>
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Supporting classification
                                                evidence ·{' '}
                                                {
                                                    candidate.evidence
                                                        .inferred_topic_provenance
                                                }
                                                . Validation Search is still
                                                required.
                                            </p>
                                        </div>
                                    )}

                                    {videoIds.length > 0 && (
                                        <div className="flex flex-wrap gap-x-3 gap-y-2 text-xs">
                                            {videoIds
                                                .slice(0, 4)
                                                .map((videoId, index) => (
                                                    <a
                                                        key={videoId}
                                                        href={`https://www.youtube.com/watch?v=${encodeURIComponent(videoId)}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 text-primary hover:underline"
                                                    >
                                                        Evidence video{' '}
                                                        {index + 1}
                                                        <ExternalLink className="size-3" />
                                                    </a>
                                                ))}
                                        </div>
                                    )}

                                    {videoIds.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {analyzerRunIds.length > 0 ? (
                                                analyzerRunIds
                                                    .slice(0, 2)
                                                    .map((runId, index) => (
                                                        <Button
                                                            key={runId}
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/analyzer/runs/${runId}?return_to=${encodeURIComponent(returnTo)}`}
                                                            >
                                                                <ScanSearch />{' '}
                                                                Analyzer
                                                                evidence{' '}
                                                                {index + 1}
                                                            </Link>
                                                        </Button>
                                                    ))
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/analyzer?video=${encodeURIComponent(videoIds[0])}&origin=discover&origin_reference=${encodeURIComponent(candidate.public_id)}&return_to=${encodeURIComponent(returnTo)}`}
                                                    >
                                                        <ScanSearch /> Analyze
                                                        evidence video
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    )}

                                    <FavoriteToggle
                                        library={library}
                                        targetType="niche_candidate"
                                        targetReference={candidate.public_id}
                                        label={candidate.phrase}
                                    />

                                    <WorkspaceHandoff
                                        workspaces={workspaces}
                                        targetType="niche_candidate"
                                        targetReference={candidate.public_id}
                                    />

                                    {candidate.validation_run ? (
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={`/research/runs/${candidate.validation_run.public_id}?return_to=${encodeURIComponent(returnTo)}`}
                                            >
                                                <CheckCircle2 aria-hidden="true" />
                                                Open validation ·{' '}
                                                {
                                                    candidate.validation_run
                                                        .status
                                                }
                                            </Link>
                                        </Button>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            <Form
                                                action={`/discover/candidates/${candidate.public_id}`}
                                                method="patch"
                                                disableWhileProcessing
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="status"
                                                            value={
                                                                candidate.status ===
                                                                'saved'
                                                                    ? 'new'
                                                                    : 'saved'
                                                            }
                                                        />
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {processing ? (
                                                                <Spinner />
                                                            ) : (
                                                                <Bookmark />
                                                            )}
                                                            {candidate.status ===
                                                            'saved'
                                                                ? 'Unsave'
                                                                : 'Save'}
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                            <Form
                                                action={`/discover/candidates/${candidate.public_id}`}
                                                method="patch"
                                                disableWhileProcessing
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="status"
                                                            value={
                                                                candidate.status ===
                                                                'dismissed'
                                                                    ? 'new'
                                                                    : 'dismissed'
                                                            }
                                                        />
                                                        <Button
                                                            type="submit"
                                                            variant="ghost"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {processing ? (
                                                                <Spinner />
                                                            ) : (
                                                                <EyeOff />
                                                            )}
                                                            {candidate.status ===
                                                            'dismissed'
                                                                ? 'Restore'
                                                                : 'Dismiss'}
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                            <Form
                                                action={`/discover/candidates/${candidate.public_id}/validate`}
                                                method="post"
                                                disableWhileProcessing
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="requested_result_count"
                                                            value={
                                                                validationDepth
                                                            }
                                                        />
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                processing ||
                                                                validationBlocked
                                                            }
                                                        >
                                                            {processing ? (
                                                                <Spinner />
                                                            ) : (
                                                                <SearchCheck />
                                                            )}
                                                            Validate
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
