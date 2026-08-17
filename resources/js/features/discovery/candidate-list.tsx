import { Form, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Bookmark,
    ChevronDown,
    ChevronRight,
    EyeOff,
    MoreHorizontal,
    ScanSearch,
    SearchCheck,
    Sparkles,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { ConfidenceBadge } from '@/components/confidence-badge';
import { StatePanel } from '@/components/data-state';
import { PaginationControls } from '@/components/pagination-controls';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { WorkspaceHandoff } from '@/features/integration/workspace-handoff';
import type { WorkspaceOption } from '@/features/integration/workspace-handoff';
import { FavoriteToggle } from '@/features/library/favorite-toggle';
import type {
    DiscoveryCandidateSort,
    DiscoveryCandidateTable,
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

function exactNumber(value: number | null | undefined, suffix = '') {
    return value === null || value === undefined
        ? 'Unavailable'
        : `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 4 }).format(value)}${suffix}`;
}

export function CandidateList({
    table,
    validationBlocked,
    library,
    workspaces,
    discoveryRunPublicId,
}: {
    table: DiscoveryCandidateTable;
    validationBlocked: boolean;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
    discoveryRunPublicId: string;
}) {
    const [validationDepth, setValidationDepth] = useState(50);
    const total =
        table.candidate_niches.total + table.weak_phrase_signals.total;

    const navigate = (changes: Record<string, string | number>) => {
        const params = new URLSearchParams(window.location.search);
        Object.entries(changes).forEach(([key, value]) =>
            params.set(key, String(value)),
        );
        router.get(
            `/discover/runs/${discoveryRunPublicId}`,
            Object.fromEntries(params.entries()),
            {
                only: ['candidate_table'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };
    const updateFilters = (changes: Record<string, string | number>) =>
        navigate({ ...changes, candidate_page: 1, weak_page: 1 });

    if (
        total === 0 &&
        table.filters.status === 'all' &&
        table.filters.minimum_score === 0 &&
        table.filters.minimum_confidence === 0
    ) {
        return (
            <StatePanel
                title="No breakout candidates found"
                description="The stored samples did not contain a recurring phrase signal. Try broader samples, a different market, or more seed runs."
                icon={Sparkles}
            />
        );
    }

    return (
        <div className="space-y-5">
            <Card className="py-4 shadow-none">
                <CardContent className="grid gap-4 px-4 xl:grid-cols-5">
                    <FilterSelect
                        id="candidate-status"
                        label="Status"
                        value={table.filters.status}
                        onChange={(value) =>
                            updateFilters({ candidate_status: value })
                        }
                        options={[
                            ['all', 'All statuses'],
                            ['new', 'New'],
                            ['saved', 'Saved'],
                            ['dismissed', 'Dismissed'],
                            ['validated', 'Validated'],
                        ]}
                    />
                    <FilterSelect
                        id="minimum-score"
                        label="Minimum evidence score"
                        value={table.filters.minimum_score}
                        onChange={(value) =>
                            updateFilters({ candidate_minimum_score: value })
                        }
                        options={[
                            [0, 'Any score'],
                            [40, '40+'],
                            [60, '60+'],
                            [80, '80+'],
                        ]}
                    />
                    <FilterSelect
                        id="minimum-confidence"
                        label="Minimum confidence"
                        value={table.filters.minimum_confidence}
                        onChange={(value) =>
                            updateFilters({
                                candidate_minimum_confidence: value,
                            })
                        }
                        options={[
                            [0, 'Any confidence'],
                            [40, '40+'],
                            [60, '60+'],
                            [80, '80+'],
                        ]}
                    />
                    <FilterSelect
                        id="candidate-sort"
                        label="Sort"
                        value={table.filters.sort}
                        onChange={(value) =>
                            updateFilters({ candidate_sort: value })
                        }
                        options={[
                            ['evidence_score', 'Evidence score'],
                            ['confidence', 'Confidence'],
                            ['videos', 'Videos'],
                            ['channels', 'Unique channels'],
                            ['small_channel_proof', 'Small-channel proof'],
                            ['typical_performance', 'Typical performance'],
                            ['stability', 'Stability'],
                            ['status', 'Status'],
                            ['theme', 'Theme'],
                        ]}
                    />
                    <FilterSelect
                        id="validation-depth"
                        label="Validation depth"
                        value={validationDepth}
                        onChange={(value) => setValidationDepth(Number(value))}
                        options={[
                            [25, '25 results · 1 call'],
                            [50, '50 results · 1 call'],
                            [100, '100 results · 2 calls'],
                        ]}
                    />
                </CardContent>
            </Card>

            {validationBlocked && (
                <Alert className="border-warning/40 bg-warning/10">
                    <SearchCheck aria-hidden="true" />
                    <AlertTitle>
                        Validation is paused by the local quota estimate
                    </AlertTitle>
                    <AlertDescription>
                        Inspection remains quota-free. Wait for the Pacific Time
                        reset or check Google Cloud Console before validating.
                    </AlertDescription>
                </Alert>
            )}

            <CandidateSection
                title="Candidate niches"
                description="Themes that met the frozen evidence thresholds. Evidence score is not Opportunity score."
                page={table.candidate_niches}
                sort={table.filters.sort}
                direction={table.filters.direction}
                pageName="candidate_page"
                empty="No candidate niches match these filters."
                {...{
                    navigate,
                    validationDepth,
                    validationBlocked,
                    library,
                    workspaces,
                    discoveryRunPublicId,
                }}
            />
            <CandidateSection
                title="Weak phrase signals"
                description="Below-threshold phrases stay inspectable with every exact insufficiency reason."
                page={table.weak_phrase_signals}
                sort={table.filters.sort}
                direction={table.filters.direction}
                pageName="weak_page"
                empty="No weak phrase signals match these filters."
                weak
                {...{
                    navigate,
                    validationDepth,
                    validationBlocked,
                    library,
                    workspaces,
                    discoveryRunPublicId,
                }}
            />
        </div>
    );
}

function FilterSelect({
    id,
    label,
    value,
    onChange,
    options,
}: {
    id: string;
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    options: (string | number)[][];
}) {
    return (
        <div className="grid gap-2">
            <label htmlFor={id} className="text-xs font-medium">
                {label}
            </label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
            >
                {options.map(([option, text]) => (
                    <option key={option} value={option}>
                        {text}
                    </option>
                ))}
            </select>
        </div>
    );
}

type CandidatePage = DiscoveryCandidateTable['candidate_niches'];

function CandidateSection({
    title,
    description,
    page,
    sort,
    direction,
    pageName,
    empty,
    weak = false,
    navigate,
    validationDepth,
    validationBlocked,
    library,
    workspaces,
    discoveryRunPublicId,
}: {
    title: string;
    description: string;
    page: CandidatePage;
    sort: DiscoveryCandidateSort;
    direction: 'asc' | 'desc';
    pageName: string;
    empty: string;
    weak?: boolean;
    navigate: (changes: Record<string, string | number>) => void;
    validationDepth: number;
    validationBlocked: boolean;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
    discoveryRunPublicId: string;
}) {
    const [expanded, setExpanded] = useState<string[]>([]);
    const toggle = (id: string) =>
        setExpanded((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    const changeSort = (next: DiscoveryCandidateSort) =>
        navigate({
            candidate_sort: next,
            candidate_direction:
                sort === next && direction === 'desc' ? 'asc' : 'desc',
            candidate_page: 1,
            weak_page: 1,
        });

    return (
        <section
            aria-labelledby={`${pageName}-title`}
            className={
                weak
                    ? 'rounded-xl border border-warning/40 bg-warning/5 p-4'
                    : 'rounded-xl border bg-card p-4'
            }
        >
            <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2
                        id={`${pageName}-title`}
                        className="text-base font-semibold"
                    >
                        {title}{' '}
                        <span className="text-muted-foreground tabular-nums">
                            ({page.total})
                        </span>
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        navigate({
                            candidate_direction:
                                direction === 'desc' ? 'asc' : 'desc',
                            candidate_page: 1,
                            weak_page: 1,
                        })
                    }
                >
                    {direction === 'desc' ? 'Descending' : 'Ascending'}
                </Button>
            </div>
            {page.data.length === 0 ? (
                <StatePanel
                    title={empty}
                    description="Adjust the status, score, or confidence filters. Inspection never calls YouTube."
                    icon={weak ? AlertTriangle : Sparkles}
                />
            ) : (
                <>
                    <Table className="min-w-[1040px] table-fixed">
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    label="Theme"
                                    value="theme"
                                    {...{ sort, direction, changeSort }}
                                    className="w-64"
                                />
                                <SortableHead
                                    label="Evidence"
                                    value="evidence_score"
                                    {...{ sort, direction, changeSort }}
                                />
                                <SortableHead
                                    label="Confidence"
                                    value="confidence"
                                    {...{ sort, direction, changeSort }}
                                />
                                <SortableHead
                                    label="Videos"
                                    value="videos"
                                    {...{ sort, direction, changeSort }}
                                />
                                <SortableHead
                                    label="Channels"
                                    value="channels"
                                    {...{ sort, direction, changeSort }}
                                />
                                <SortableHead
                                    label="Small proof"
                                    value="small_channel_proof"
                                    {...{ sort, direction, changeSort }}
                                />
                                <SortableHead
                                    label="Typical / day"
                                    value="typical_performance"
                                    {...{ sort, direction, changeSort }}
                                />
                                <SortableHead
                                    label="Stability"
                                    value="stability"
                                    {...{ sort, direction, changeSort }}
                                />
                                <SortableHead
                                    label="Status"
                                    value="status"
                                    {...{ sort, direction, changeSort }}
                                />
                                <TableHead className="w-36">Decision</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {page.data.map((candidate) => {
                                const isExpanded = expanded.includes(
                                    candidate.public_id,
                                );

                                return (
                                    <Fragment key={candidate.public_id}>
                                        <CandidateRow
                                            candidate={candidate}
                                            expanded={isExpanded}
                                            onToggle={() =>
                                                toggle(candidate.public_id)
                                            }
                                            {...{
                                                validationDepth,
                                                validationBlocked,
                                                discoveryRunPublicId,
                                            }}
                                        />
                                        {isExpanded && (
                                            <EvidenceRow
                                                candidate={candidate}
                                                {...{
                                                    library,
                                                    workspaces,
                                                    discoveryRunPublicId,
                                                }}
                                            />
                                        )}
                                    </Fragment>
                                );
                            })}
                        </TableBody>
                    </Table>
                    <div className="mt-3">
                        <PaginationControls
                            pagination={page}
                            ariaLabel={`${title} pagination`}
                            onPageChange={(next) =>
                                navigate({ [pageName]: next })
                            }
                        />
                    </div>
                </>
            )}
        </section>
    );
}

function SortableHead({
    label,
    value,
    sort,
    direction,
    changeSort,
    className,
}: {
    label: string;
    value: DiscoveryCandidateSort;
    sort: DiscoveryCandidateSort;
    direction: 'asc' | 'desc';
    changeSort: (sort: DiscoveryCandidateSort) => void;
    className?: string;
}) {
    return (
        <TableHead className={className}>
            <button
                type="button"
                className="inline-flex items-center gap-1 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                onClick={() => changeSort(value)}
            >
                {label}
                {sort === value && (
                    <span aria-label={direction}>
                        {direction === 'desc' ? '↓' : '↑'}
                    </span>
                )}
            </button>
        </TableHead>
    );
}

function CandidateRow({
    candidate,
    expanded,
    onToggle,
    validationDepth,
    validationBlocked,
    discoveryRunPublicId,
}: {
    candidate: NicheCandidate;
    expanded: boolean;
    onToggle: () => void;
    validationDepth: number;
    validationBlocked: boolean;
    discoveryRunPublicId: string;
}) {
    const evidence = candidate.evidence;
    const returnTo = `/discover/runs/${discoveryRunPublicId}`;

    return (
        <TableRow
            className={
                candidate.evidence_state === 'weak_phrase_signal'
                    ? 'bg-warning/5'
                    : undefined
            }
        >
            <TableCell className="align-top">
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={expanded}
                    aria-controls={`evidence-${candidate.public_id}`}
                    className="flex max-w-full items-start gap-2 text-left font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {expanded ? (
                        <ChevronDown className="mt-0.5 size-4 shrink-0" />
                    ) : (
                        <ChevronRight className="mt-0.5 size-4 shrink-0" />
                    )}
                    <span className="break-words">{candidate.phrase}</span>
                </button>
            </TableCell>
            <TableCell className="tabular-nums">
                {exactNumber(candidate.overall_score)}
            </TableCell>
            <TableCell>
                <ConfidenceBadge
                    score={candidate.confidence_score ?? 0}
                    label={confidenceLabel(candidate.confidence_score ?? 0)}
                />
            </TableCell>
            <TableCell className="tabular-nums">
                {evidence.source_video_count ?? evidence.video_ids?.length ?? 0}
            </TableCell>
            <TableCell className="tabular-nums">
                {evidence.unique_channel_count ??
                    evidence.channel_ids?.length ??
                    0}
            </TableCell>
            <TableCell className="tabular-nums">
                {evidence.small_channel_proof_count ?? 0}
            </TableCell>
            <TableCell className="tabular-nums">
                {exactNumber(evidence.typical_median_views_per_day)}
            </TableCell>
            <TableCell className="tabular-nums">
                {exactNumber(evidence.stability_score)}
            </TableCell>
            <TableCell>
                <Badge variant="outline">{statusLabel(candidate.status)}</Badge>
            </TableCell>
            <TableCell className="align-top">
                {candidate.validation_run ? (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={`/research/runs/${candidate.validation_run.public_id}?return_to=${encodeURIComponent(returnTo)}`}
                        >
                            Open validation
                        </Link>
                    </Button>
                ) : (
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
                                    value={validationDepth}
                                />
                                <Button
                                    size="sm"
                                    type="submit"
                                    disabled={processing || validationBlocked}
                                >
                                    {processing ? <Spinner /> : <SearchCheck />}{' '}
                                    Validate
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </TableCell>
        </TableRow>
    );
}

function EvidenceRow({
    candidate,
    library,
    workspaces,
    discoveryRunPublicId,
}: {
    candidate: NicheCandidate;
    library: LibraryContext;
    workspaces: WorkspaceOption[];
    discoveryRunPublicId: string;
}) {
    const evidence = candidate.evidence;
    const videos = evidence.video_ids ?? [];
    const channels = evidence.channel_ids ?? [];
    const analyzerRuns = evidence.analyzer_run_ids ?? [];
    const reasons = evidence.insufficiency_reasons ?? [];
    const returnTo = `/discover/runs/${discoveryRunPublicId}`;
    const uniqueChannels = evidence.unique_channel_count ?? channels.length;
    const smallProof = evidence.small_channel_proof_count ?? 0;

    return (
        <TableRow
            id={`evidence-${candidate.public_id}`}
            className="bg-muted/20 hover:bg-muted/20"
        >
            <TableCell colSpan={10} className="p-5">
                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.7fr)]">
                    <div className="space-y-4">
                        <div>
                            <h3 className="font-semibold">Expanded evidence</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {candidate.summary ?? 'No stored description.'}
                            </p>
                        </div>
                        {reasons.length > 0 && (
                            <Alert className="border-warning/40 bg-warning/10">
                                <AlertTriangle />
                                <AlertTitle>
                                    Why this remains a weak signal
                                </AlertTitle>
                                <AlertDescription>
                                    <ul className="mt-2 list-disc space-y-1 pl-4">
                                        {reasons.map((reason) => (
                                            <li key={reason}>{reason}</li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}
                        <dl className="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                            <EvidenceValue
                                label="Suggested query"
                                value={
                                    evidence.suggested_validation_query ??
                                    candidate.phrase
                                }
                            />
                            <EvidenceValue
                                label="Typical median views/day"
                                value={exactNumber(
                                    evidence.typical_median_views_per_day,
                                )}
                            />
                            <EvidenceValue
                                label="Outlier-free median views/day"
                                value={exactNumber(
                                    evidence.outlier_free_median_views_per_day,
                                )}
                            />
                            <EvidenceValue
                                label="Top-video share"
                                value={exactNumber(
                                    evidence.top_video_performance_share ===
                                        null ||
                                        evidence.top_video_performance_share ===
                                            undefined
                                        ? null
                                        : evidence.top_video_performance_share *
                                              100,
                                    '%',
                                )}
                            />
                            <EvidenceValue
                                label="Stability"
                                value={exactNumber(evidence.stability_score)}
                            />
                            <EvidenceValue
                                label="Small-channel proof"
                                value={`${smallProof} of ${uniqueChannels} unique channels`}
                            />
                            <EvidenceValue
                                label="Other / unavailable channel size"
                                value={String(
                                    Math.max(0, uniqueChannels - smallProof),
                                )}
                            />
                            <EvidenceValue
                                label="Formula"
                                value={
                                    candidate.formula_version ??
                                    'Legacy / unavailable'
                                }
                            />
                        </dl>
                        <EvidenceList
                            label="Included phrases"
                            values={
                                evidence.original_phrases ??
                                evidence.member_phrases ??
                                []
                            }
                        />
                        <EvidenceList label="Channel IDs" values={channels} />
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Evidence videos
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {videos.length === 0 ? (
                                    <span className="text-sm text-muted-foreground">
                                        Unavailable
                                    </span>
                                ) : (
                                    videos.map((id) => (
                                        <a
                                            key={id}
                                            href={`https://www.youtube.com/watch?v=${encodeURIComponent(id)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {id}
                                        </a>
                                    ))
                                )}
                            </div>
                        </div>
                        <EvidenceList
                            label="Sources"
                            values={evidence.evidence_provenance ?? []}
                        />
                        <EvidenceList
                            label="Risks"
                            values={
                                reasons.length > 0
                                    ? reasons
                                    : [
                                          evidence.outlier_dependent
                                              ? 'Performance is outlier-dependent.'
                                              : 'No frozen threshold risk was recorded.',
                                      ]
                            }
                        />
                    </div>
                    <details className="self-start rounded-lg border bg-card p-3">
                        <summary className="flex cursor-pointer list-none items-center gap-2 font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                            <MoreHorizontal className="size-4" /> Secondary
                            actions
                        </summary>
                        <div className="mt-3 space-y-3 border-t pt-3">
                            <div className="flex flex-wrap gap-2">
                                <StatusAction
                                    candidate={candidate}
                                    status={
                                        candidate.status === 'saved'
                                            ? 'new'
                                            : 'saved'
                                    }
                                    label={
                                        candidate.status === 'saved'
                                            ? 'Unsave'
                                            : 'Save'
                                    }
                                    icon="save"
                                />
                                <StatusAction
                                    candidate={candidate}
                                    status={
                                        candidate.status === 'dismissed'
                                            ? 'new'
                                            : 'dismissed'
                                    }
                                    label={
                                        candidate.status === 'dismissed'
                                            ? 'Restore'
                                            : 'Dismiss'
                                    }
                                    icon="dismiss"
                                />
                            </div>
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
                            <div className="flex flex-wrap gap-2">
                                {analyzerRuns.length > 0
                                    ? analyzerRuns
                                          .slice(0, 2)
                                          .map((id, index) => (
                                              <Button
                                                  key={id}
                                                  size="sm"
                                                  variant="outline"
                                                  asChild
                                              >
                                                  <Link
                                                      href={`/analyzer/runs/${id}?return_to=${encodeURIComponent(returnTo)}`}
                                                  >
                                                      <ScanSearch /> Analyzer
                                                      evidence {index + 1}
                                                  </Link>
                                              </Button>
                                          ))
                                    : videos[0] && (
                                          <Button
                                              size="sm"
                                              variant="outline"
                                              asChild
                                          >
                                              <Link
                                                  href={`/analyzer?video=${encodeURIComponent(videos[0])}&origin=discover&origin_reference=${encodeURIComponent(candidate.public_id)}&return_to=${encodeURIComponent(returnTo)}`}
                                              >
                                                  <ScanSearch /> Analyze
                                                  evidence video
                                              </Link>
                                          </Button>
                                      )}
                            </div>
                        </div>
                    </details>
                </div>
            </TableCell>
        </TableRow>
    );
}

function EvidenceValue({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border bg-background p-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium break-words tabular-nums">
                {value}
            </dd>
        </div>
    );
}
function EvidenceList({ label, values }: { label: string; values: string[] }) {
    return (
        <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="mt-2 flex flex-wrap gap-2">
                {values.length === 0 ? (
                    <span className="text-sm text-muted-foreground">
                        Unavailable
                    </span>
                ) : (
                    values.map((value) => (
                        <Badge
                            key={value}
                            variant="outline"
                            className="max-w-full whitespace-normal"
                        >
                            {value}
                        </Badge>
                    ))
                )}
            </div>
        </div>
    );
}
function StatusAction({
    candidate,
    status,
    label,
    icon,
}: {
    candidate: NicheCandidate;
    status: 'new' | 'saved' | 'dismissed';
    label: string;
    icon: 'save' | 'dismiss';
}) {
    return (
        <Form
            action={`/discover/candidates/${candidate.public_id}`}
            method="patch"
            disableWhileProcessing
        >
            {({ processing }) => (
                <>
                    <input type="hidden" name="status" value={status} />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={processing}
                    >
                        {processing ? (
                            <Spinner />
                        ) : icon === 'save' ? (
                            <Bookmark />
                        ) : (
                            <EyeOff />
                        )}
                        {label}
                    </Button>
                </>
            )}
        </Form>
    );
}
