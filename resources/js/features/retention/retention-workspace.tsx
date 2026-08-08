import { Form } from '@inertiajs/react';
import {
    ArchiveX,
    CheckCircle2,
    Database,
    FileClock,
    Heart,
    History,
    LoaderCircle,
    ShieldAlert,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import FormStatus from '@/components/form-status';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type {
    RetentionAudit,
    RetentionPreviewRun,
    RetentionWorkspaceData,
} from '@/types';

const number = new Intl.NumberFormat('en-US');

export function RetentionWorkspace({
    data,
    timezone,
}: {
    data: RetentionWorkspaceData;
    timezone: string;
}) {
    const { preview, history, has_active: hasActive } = data;
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const [bulkDialogOpen, setBulkDialogOpen] = useState(false);
    const [cleanupDialogOpen, setCleanupDialogOpen] = useState(false);
    const selectedRuns = useMemo(
        () => preview.runs.filter((run) => selectedIds.includes(run.public_id)),
        [preview.runs, selectedIds],
    );
    const selectedArtifacts = selectedRuns.reduce(
        (sum, run) => sum + run.artifacts,
        0,
    );
    const selectedFavorites = selectedRuns.filter(
        (run) => run.favorite_impacted,
    ).length;
    const eligibleRuns = preview.counts.research_runs;
    const expiredExports = preview.counts.expired_exports;
    const preservedFavorites = preview.counts.preserved_favorites;
    const nothingDue = eligibleRuns === 0 && expiredExports === 0;

    function toggleRun(publicId: string, checked: boolean) {
        setSelectedIds((current) =>
            checked
                ? Array.from(new Set([...current, publicId]))
                : current.filter((id) => id !== publicId),
        );
    }

    function toggleAll(checked: boolean) {
        setSelectedIds(checked ? preview.runs.map((run) => run.public_id) : []);
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                <Database
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <div className="space-y-1">
                                <CardTitle>
                                    Six-month retention preview
                                </CardTitle>
                                <CardDescription className="max-w-2xl leading-6">
                                    Runs completed or failed before{' '}
                                    <strong className="font-medium text-foreground">
                                        {formatDate(
                                            preview.cutoff_at,
                                            timezone,
                                        )}
                                    </strong>{' '}
                                    are eligible. Projects, saved queries,
                                    users, settings, and favorited runs are
                                    preserved.
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            {hasActive && (
                                <LoaderCircle
                                    className="size-3.5 animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            {hasActive
                                ? 'Updating cleanup status'
                                : `Updated ${formatDate(data.refreshed_at, timezone)}`}
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Summary
                            label="Eligible runs"
                            value={eligibleRuns}
                            detail="Completed or failed"
                        />
                        <Summary
                            label="Video snapshots"
                            value={preview.counts.video_snapshots}
                            detail="Immutable metrics"
                        />
                        <Summary
                            label="Channel snapshots"
                            value={preview.counts.channel_snapshots}
                            detail="Immutable metrics"
                        />
                        <Summary
                            label="Expired exports"
                            value={expiredExports}
                            detail="Private generated files"
                        />
                    </div>

                    {preservedFavorites > 0 && (
                        <FormStatus
                            tone="info"
                            title={`${number.format(preservedFavorites)} favorited run${preservedFavorites === 1 ? '' : 's'} preserved`}
                            message="Automatic and retention cleanup skip these runs. A selected favorited run requires a second explicit confirmation."
                        />
                    )}

                    {nothingDue ? (
                        <FormStatus
                            tone="success"
                            title="Nothing is due for cleanup"
                            message="No non-favorited historical runs or expired export files currently meet the retention rules. You can still record a dry-run audit."
                        />
                    ) : (
                        <div className="rounded-xl border border-destructive/25 bg-destructive/5 p-4">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p className="font-medium">
                                        Retention cleanup is destructive
                                    </p>
                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        This will delete{' '}
                                        {number.format(eligibleRuns)} eligible
                                        run{eligibleRuns === 1 ? '' : 's'} and{' '}
                                        {number.format(expiredExports)} expired
                                        export file
                                        {expiredExports === 1 ? '' : 's'}.
                                        Favorited runs stay intact.
                                    </p>
                                </div>
                                <Dialog
                                    open={cleanupDialogOpen}
                                    onOpenChange={setCleanupDialogOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button variant="destructive">
                                            <ArchiveX aria-hidden="true" />
                                            Review cleanup
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Delete all eligible retained
                                                data?
                                            </DialogTitle>
                                            <DialogDescription>
                                                This queues deletion of{' '}
                                                {number.format(eligibleRuns)}{' '}
                                                run
                                                {eligibleRuns === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                and{' '}
                                                {number.format(expiredExports)}{' '}
                                                expired export file
                                                {expiredExports === 1
                                                    ? ''
                                                    : 's'}
                                                . This cannot be undone.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            action="/settings/retention/cleanup"
                                            method="post"
                                            disableWhileProcessing
                                            onSuccess={() =>
                                                setCleanupDialogOpen(false)
                                            }
                                            className="space-y-4"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <ConfirmationCheckbox
                                                        id="retention-confirmation"
                                                        name="confirmation"
                                                        label={`I confirm deletion of ${number.format(eligibleRuns)} eligible run${eligibleRuns === 1 ? '' : 's'} and ${number.format(expiredExports)} expired export file${expiredExports === 1 ? '' : 's'}.`}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.confirmation
                                                        }
                                                    />
                                                    <DialogFooter>
                                                        <DialogClose asChild>
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                            >
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            variant="destructive"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {processing ? (
                                                                <Spinner />
                                                            ) : (
                                                                <Trash2 aria-hidden="true" />
                                                            )}
                                                            {processing
                                                                ? 'Queueing cleanup…'
                                                                : 'Delete eligible data'}
                                                        </Button>
                                                    </DialogFooter>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                    )}

                    <Form
                        action="/settings/retention/preview"
                        method="post"
                        disableWhileProcessing
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <FileClock aria-hidden="true" />
                                )}
                                {processing
                                    ? 'Recording dry run…'
                                    : 'Record dry run'}
                            </Button>
                        )}
                    </Form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <CardTitle>Select historical snapshots</CardTitle>
                            <CardDescription className="mt-1">
                                Choose exact run snapshots to delete. Favorited
                                selections are clearly marked and require
                                confirmation.
                            </CardDescription>
                        </div>
                        <Dialog
                            open={bulkDialogOpen}
                            onOpenChange={setBulkDialogOpen}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    variant="destructive"
                                    disabled={selectedRuns.length === 0}
                                >
                                    <Trash2 aria-hidden="true" />
                                    Delete selected ({selectedRuns.length})
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Delete {selectedRuns.length} selected
                                        snapshot
                                        {selectedRuns.length === 1 ? '' : 's'}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        The selection contains{' '}
                                        {number.format(selectedArtifacts)}{' '}
                                        stored artifact
                                        {selectedArtifacts === 1 ? '' : 's'}
                                        {selectedFavorites > 0
                                            ? ` and ${selectedFavorites} favorited run${selectedFavorites === 1 ? '' : 's'}`
                                            : ''}
                                        . Saved queries and projects remain.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    action="/settings/retention/runs"
                                    method="delete"
                                    disableWhileProcessing
                                    onSuccess={() => {
                                        setBulkDialogOpen(false);
                                        setSelectedIds([]);
                                    }}
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            {selectedIds.map((publicId) => (
                                                <input
                                                    key={publicId}
                                                    type="hidden"
                                                    name="research_run_ids[]"
                                                    value={publicId}
                                                />
                                            ))}
                                            <ConfirmationCheckbox
                                                id="selection-confirmation"
                                                name="confirmation"
                                                label={`I confirm permanent deletion of ${selectedRuns.length} selected snapshot${selectedRuns.length === 1 ? '' : 's'}.`}
                                            />
                                            <InputError
                                                message={errors.confirmation}
                                            />
                                            {selectedFavorites > 0 && (
                                                <>
                                                    <ConfirmationCheckbox
                                                        id="favorite-impact-confirmation"
                                                        name="favorite_impact_confirmed"
                                                        label={`I understand this also removes ${selectedFavorites} favorite${selectedFavorites === 1 ? '' : 's'}.`}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.favorite_impact_confirmed
                                                        }
                                                    />
                                                </>
                                            )}
                                            <DialogFooter>
                                                <DialogClose asChild>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                    >
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    {processing ? (
                                                        <Spinner />
                                                    ) : (
                                                        <Trash2 aria-hidden="true" />
                                                    )}
                                                    {processing
                                                        ? 'Queueing deletion…'
                                                        : 'Delete selected snapshots'}
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardHeader>
                <CardContent>
                    {preview.runs.length === 0 ? (
                        <div className="rounded-xl border border-dashed px-6 py-10 text-center">
                            <CheckCircle2
                                className="mx-auto size-7 text-success-foreground"
                                aria-hidden="true"
                            />
                            <p className="mt-3 font-medium">
                                No historical snapshots are eligible
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Runs become visible here after crossing the
                                six-month cutoff.
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">
                                        <Checkbox
                                            aria-label="Select all historical snapshots"
                                            checked={
                                                selectedIds.length ===
                                                preview.runs.length
                                                    ? true
                                                    : selectedIds.length > 0
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) =>
                                                toggleAll(checked === true)
                                            }
                                        />
                                    </TableHead>
                                    <TableHead>Run</TableHead>
                                    <TableHead>Collected / failed</TableHead>
                                    <TableHead className="text-right">
                                        Artifacts
                                    </TableHead>
                                    <TableHead>Protection</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {preview.runs.map((run) => (
                                    <SnapshotRow
                                        key={run.public_id}
                                        run={run}
                                        timezone={timezone}
                                        selected={selectedIds.includes(
                                            run.public_id,
                                        )}
                                        onSelected={(checked) =>
                                            toggleRun(run.public_id, checked)
                                        }
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <AuditHistory history={history} timezone={timezone} />
        </div>
    );
}

function SnapshotRow({
    run,
    timezone,
    selected,
    onSelected,
}: {
    run: RetentionPreviewRun;
    timezone: string;
    selected: boolean;
    onSelected: (checked: boolean) => void;
}) {
    return (
        <TableRow className={cn(selected && 'bg-muted/50')}>
            <TableCell>
                <Checkbox
                    aria-label={`Select ${run.query_text}`}
                    checked={selected}
                    onCheckedChange={(checked) => onSelected(checked === true)}
                />
            </TableCell>
            <TableCell className="min-w-64">
                <p
                    className="max-w-md truncate font-medium"
                    title={run.query_text}
                >
                    {run.query_text}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    {number.format(run.video_snapshots)} video ·{' '}
                    {number.format(run.channel_snapshots)} channel ·{' '}
                    {number.format(run.opportunity_scores)} score
                </p>
            </TableCell>
            <TableCell className="whitespace-nowrap">
                <span className="capitalize">{run.status}</span>
                <span className="block text-xs text-muted-foreground">
                    {formatDate(run.terminal_at, timezone)}
                </span>
            </TableCell>
            <TableCell className="text-right font-medium tabular-nums">
                {number.format(run.artifacts)}
            </TableCell>
            <TableCell>
                {run.favorite_impacted ? (
                    <Badge
                        variant="outline"
                        className="border-rose-300 text-rose-700 dark:text-rose-300"
                    >
                        <Heart aria-hidden="true" /> Favorited
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Retention eligible
                    </span>
                )}
            </TableCell>
        </TableRow>
    );
}

function AuditHistory({
    history,
    timezone,
}: {
    history: RetentionAudit[];
    timezone: string;
}) {
    const failed = history.find((entry) => entry.status === 'failed');

    return (
        <Card>
            <CardHeader>
                <div className="flex items-start gap-3">
                    <History
                        className="mt-0.5 size-5 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <CardTitle>Cleanup audit history</CardTitle>
                        <CardDescription className="mt-1">
                            Recent dry runs and executions for this account,
                            including exact per-target outcomes.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {failed && (
                    <FormStatus
                        tone="error"
                        title="A cleanup needs attention"
                        message={
                            failed.error_message ??
                            'Cleanup failed safely. Refresh to review the stored audit before retrying.'
                        }
                    />
                )}
                {history.length === 0 ? (
                    <div className="rounded-xl border border-dashed px-6 py-10 text-center">
                        <History
                            className="mx-auto size-7 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            No cleanup activity yet
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Record a dry run to create the first non-destructive
                            audit entry.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {history.map((entry) => (
                            <AuditEntry
                                key={entry.public_id}
                                entry={entry}
                                timezone={timezone}
                            />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function AuditEntry({
    entry,
    timezone,
}: {
    entry: RetentionAudit;
    timezone: string;
}) {
    const skipped = entry.items.filter((item) =>
        item.outcome.startsWith('skipped'),
    ).length;
    const deleted = entry.items.filter(
        (item) => item.outcome === 'deleted',
    ).length;
    const preserved = entry.items.filter(
        (item) => item.outcome === 'preserved_favorite',
    ).length;
    const active = entry.status === 'queued' || entry.status === 'processing';

    return (
        <details className="group rounded-xl border p-4" open={active}>
            <summary className="flex cursor-pointer list-none flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <span
                        className={cn(
                            'rounded-full p-2',
                            auditTone(entry.status),
                        )}
                    >
                        {active ? (
                            <LoaderCircle
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        ) : entry.status === 'failed' ? (
                            <TriangleAlert
                                className="size-4"
                                aria-hidden="true"
                            />
                        ) : (
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                        )}
                    </span>
                    <div>
                        <p className="font-medium capitalize">
                            {entry.status.replaceAll('_', ' ')} ·{' '}
                            {entry.mode.replaceAll('_', ' ')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {entry.dry_run ? 'Dry run' : 'Execution'} ·{' '}
                            {entry.created_at
                                ? formatDate(entry.created_at, timezone)
                                : 'Time unavailable'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2 text-xs">
                    {deleted > 0 && (
                        <Badge variant="secondary">{deleted} deleted</Badge>
                    )}
                    {preserved > 0 && (
                        <Badge variant="outline">{preserved} preserved</Badge>
                    )}
                    {skipped > 0 && (
                        <Badge variant="outline">{skipped} skipped</Badge>
                    )}
                    {active && <Badge>Live</Badge>}
                </div>
            </summary>
            <div className="mt-4 space-y-3 border-t pt-4">
                {entry.error_message && (
                    <div className="flex gap-2 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                        <ShieldAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{entry.error_message}</span>
                    </div>
                )}
                {skipped > 0 && (
                    <div className="flex gap-2 rounded-lg bg-warning/10 p-3 text-sm text-warning-foreground">
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Cleanup completed with partial outcomes because{' '}
                            {skipped} target{skipped === 1 ? ' was' : 's were'}{' '}
                            missing or no longer eligible.
                        </span>
                    </div>
                )}
                <div className="grid gap-3 sm:grid-cols-3">
                    <AuditMetric
                        label="Eligible runs"
                        value={entry.eligible_counts.research_runs ?? 0}
                    />
                    <AuditMetric
                        label="Deleted runs"
                        value={entry.deleted_counts.research_runs ?? 0}
                    />
                    <AuditMetric
                        label="Deleted exports"
                        value={entry.deleted_counts.expired_exports ?? 0}
                    />
                </div>
                {entry.items.length > 0 && (
                    <ul
                        className="max-h-56 space-y-2 overflow-y-auto"
                        aria-label="Cleanup target outcomes"
                    >
                        {entry.items.map((item) => (
                            <li
                                key={`${item.target_type}-${item.target_reference}`}
                                className="flex flex-col gap-1 rounded-lg bg-muted/40 px-3 py-2 text-xs sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span className="truncate font-mono">
                                    {item.target_type.replaceAll('_', ' ')} ·{' '}
                                    {item.target_reference}
                                </span>
                                <span className="whitespace-nowrap text-muted-foreground capitalize">
                                    {item.outcome.replaceAll('_', ' ')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </details>
    );
}

function Summary({
    label,
    value,
    detail,
}: {
    label: string;
    value: number;
    detail: string;
}) {
    return (
        <div className="rounded-xl border bg-muted/25 p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">
                {number.format(value)}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">{detail}</p>
        </div>
    );
}

function AuditMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border px-3 py-2">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-semibold tabular-nums">
                {number.format(value)}
            </p>
        </div>
    );
}

function ConfirmationCheckbox({
    id,
    name,
    label,
}: {
    id: string;
    name: string;
    label: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3">
            <Checkbox id={id} name={name} value="1" required />
            <Label htmlFor={id} className="leading-5">
                {label}
            </Label>
        </div>
    );
}

function formatDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function auditTone(status: RetentionAudit['status']): string {
    if (status === 'failed') {
        return 'bg-destructive/10 text-destructive';
    }

    if (status === 'queued' || status === 'processing') {
        return 'bg-info/10 text-info-foreground';
    }

    return 'bg-success/10 text-success-foreground';
}
