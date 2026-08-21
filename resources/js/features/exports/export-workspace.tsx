import { Form, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    Download,
    FileDown,
    FileSpreadsheet,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { MarketBadge } from '@/components/market-badge';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    ExportBuilderData,
    ExportFormat,
    ExportJob,
    ExportJobsData,
} from '@/types';

type FormData = {
    format: ExportFormat;
    source_type:
        'research_runs' | 'shortlist' | 'comparison' | 'topic_workspace';
    source_id: string | null;
    research_run_ids: string[];
    video_ids: string[];
    include_technical_details: boolean;
    confirmed: boolean;
    columns: string[];
};

function formatDate(value: string | null, timezone: string) {
    if (!value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function formatBytes(value: number | null) {
    if (value === null) {
        return '—';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function statusBadge(job: ExportJob) {
    if (job.expired) {
        return (
            <Badge variant="outline">
                <Clock3 />
                Expired
            </Badge>
        );
    }

    if (job.status === 'completed') {
        return (
            <Badge className="bg-success text-success-foreground">
                <CheckCircle2 />
                Ready
            </Badge>
        );
    }

    if (job.status === 'failed') {
        return (
            <Badge variant="destructive">
                <AlertTriangle />
                Failed
            </Badge>
        );
    }

    return (
        <Badge variant="secondary">
            <RefreshCw className="animate-spin" />
            {job.status === 'queued' ? 'Queued' : 'Generating'}
        </Badge>
    );
}

export function ExportWorkspace({
    builder,
    jobs,
    timezone,
    selectedRunId,
}: {
    builder: ExportBuilderData;
    jobs: ExportJobsData;
    timezone: string;
    selectedRunId: string | null;
}) {
    const form = useForm<FormData>({
        format: 'xlsx',
        source_type: 'research_runs',
        source_id: null,
        research_run_ids:
            selectedRunId &&
            builder.runs.some((run) => run.public_id === selectedRunId)
                ? [selectedRunId]
                : [],
        video_ids: [],
        include_technical_details: false,
        confirmed: false,
        columns: builder.standard_columns,
    });
    const [deleting, setDeleting] = useState<ExportJob | null>(null);
    const selectedWorkspace = useMemo(
        () =>
            builder.topic_workspaces.find(
                (workspace) => workspace.public_id === form.data.source_id,
            ),
        [builder.topic_workspaces, form.data.source_id],
    );
    const effectiveRunIds = useMemo(
        () =>
            form.data.source_type === 'shortlist'
                ? builder.shortlist_run_ids
                : form.data.source_type === 'topic_workspace'
                  ? (selectedWorkspace?.run_ids ?? [])
                  : form.data.research_run_ids,
        [
            builder.shortlist_run_ids,
            form.data.research_run_ids,
            form.data.source_type,
            selectedWorkspace?.run_ids,
        ],
    );
    const selectedRuns = useMemo(
        () =>
            builder.runs.filter((run) =>
                effectiveRunIds.includes(run.public_id),
            ),
        [builder.runs, effectiveRunIds],
    );
    const selectableVideos = selectedRuns.flatMap((run) => run.videos);
    const selectedWarnings = selectedRuns.reduce(
        (sum, run) => sum + run.warning_count,
        0,
    );
    const selectedVideoRows = selectedRuns.reduce(
        (sum, run) => sum + run.video_count,
        0,
    );

    function toggleRun(id: string, checked: boolean) {
        form.setData(
            'research_run_ids',
            checked
                ? [...form.data.research_run_ids, id]
                : form.data.research_run_ids.filter((value) => value !== id),
        );
    }

    function toggleColumn(key: string, checked: boolean) {
        form.setData(
            'columns',
            checked
                ? [...form.data.columns, key]
                : form.data.columns.filter((value) => value !== key),
        );
    }

    function toggleVideo(id: string, checked: boolean) {
        form.setData(
            'video_ids',
            checked
                ? [...form.data.video_ids, id]
                : form.data.video_ids.filter((value) => value !== id),
        );
    }

    return (
        <div className="space-y-8">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/exports', { preserveScroll: true });
                }}
                className="space-y-6"
            >
                <Card className="overflow-hidden border-primary/15">
                    <CardHeader className="border-b bg-gradient-to-r from-primary/8 via-transparent to-info/8">
                        <CardTitle>Build a research export</CardTitle>
                        <CardDescription>
                            Select completed immutable runs and exactly which
                            stored columns to include.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-7 pt-1">
                        <fieldset className="space-y-3">
                            <legend className="font-medium">
                                Stored dataset
                            </legend>
                            <p className="text-sm text-muted-foreground">
                                Export frozen local evidence only. The selected
                                source is captured before generation; no
                                provider collection runs.
                            </p>
                            <div className="grid gap-3 lg:grid-cols-2">
                                {(
                                    [
                                        [
                                            'research_runs',
                                            'Research runs',
                                            'Choose completed runs directly.',
                                        ],
                                        [
                                            'shortlist',
                                            'Shortlist',
                                            `${builder.shortlist_run_ids.length} saved run(s) available.`,
                                        ],
                                        [
                                            'comparison',
                                            'Comparison',
                                            'Choose two to five completed runs.',
                                        ],
                                    ] as const
                                ).map(([value, label, description]) => (
                                    <label
                                        key={value}
                                        className="cursor-pointer rounded-xl border p-4 has-checked:border-primary has-checked:bg-primary/5"
                                    >
                                        <input
                                            className="sr-only"
                                            type="radio"
                                            name="source_type"
                                            checked={
                                                form.data.source_type === value
                                            }
                                            onChange={() =>
                                                form.setData({
                                                    source_type: value,
                                                    source_id: null,
                                                    confirmed: false,
                                                })
                                            }
                                        />
                                        <span className="block font-semibold">
                                            {label}
                                        </span>
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            {description}
                                        </span>
                                    </label>
                                ))}
                                <label className="cursor-pointer rounded-xl border p-4 has-checked:border-primary has-checked:bg-primary/5">
                                    <input
                                        className="sr-only"
                                        type="radio"
                                        name="source_type"
                                        checked={
                                            form.data.source_type ===
                                            'topic_workspace'
                                        }
                                        onChange={() =>
                                            form.setData({
                                                source_type: 'topic_workspace',
                                                confirmed: false,
                                            })
                                        }
                                    />
                                    <span className="block font-semibold">
                                        Topic Workspace
                                    </span>
                                    <select
                                        aria-label="Topic Workspace to export"
                                        className="mt-2 h-9 w-full rounded-md border bg-background px-2 text-sm"
                                        value={form.data.source_id ?? ''}
                                        onChange={(event) =>
                                            form.setData({
                                                source_type: 'topic_workspace',
                                                source_id:
                                                    event.target.value || null,
                                                confirmed: false,
                                            })
                                        }
                                    >
                                        <option value="">
                                            Select a workspace
                                        </option>
                                        {builder.topic_workspaces.map(
                                            (workspace) => (
                                                <option
                                                    key={workspace.public_id}
                                                    value={workspace.public_id}
                                                >
                                                    {workspace.name} (
                                                    {workspace.run_ids.length}{' '}
                                                    linked run(s))
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                            </div>
                            <InputError
                                message={
                                    form.errors.source_type ??
                                    form.errors.source_id
                                }
                            />
                        </fieldset>
                        {builder.runs.length === 0 ? (
                            <Alert>
                                <FileDown />
                                <AlertTitle>
                                    No completed runs available
                                </AlertTitle>
                                <AlertDescription>
                                    Complete a research run before creating an
                                    export.
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        className="mt-3"
                                    >
                                        <Link href="/search">
                                            Start a search
                                        </Link>
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <fieldset className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <legend className="font-medium">
                                            Research runs
                                        </legend>
                                        <p className="text-sm text-muted-foreground">
                                            {form.data.source_type ===
                                            'shortlist'
                                                ? 'Your saved shortlist is used as the frozen source.'
                                                : form.data.source_type ===
                                                    'topic_workspace'
                                                  ? 'Completed runs linked to the selected workspace are used.'
                                                  : form.data.source_type ===
                                                      'comparison'
                                                    ? 'Choose two to five completed snapshots for a comparison export.'
                                                    : `Choose up to ${builder.max_runs} completed snapshots.`}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={[
                                            'shortlist',
                                            'topic_workspace',
                                        ].includes(form.data.source_type)}
                                        onClick={() =>
                                            form.setData(
                                                'research_run_ids',
                                                form.data.research_run_ids
                                                    .length ===
                                                    builder.runs.length
                                                    ? []
                                                    : builder.runs.map(
                                                          (run) =>
                                                              run.public_id,
                                                      ),
                                            )
                                        }
                                    >
                                        {form.data.research_run_ids.length ===
                                        builder.runs.length
                                            ? 'Clear all'
                                            : 'Select all'}
                                    </Button>
                                </div>
                                <div className="grid max-h-80 gap-2 overflow-y-auto rounded-xl border p-2">
                                    {builder.runs.map((run) => (
                                        <label
                                            key={run.public_id}
                                            className="grid cursor-pointer grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 rounded-lg border p-3 has-checked:border-primary has-checked:bg-primary/5"
                                        >
                                            <Checkbox
                                                checked={effectiveRunIds.includes(
                                                    run.public_id,
                                                )}
                                                disabled={[
                                                    'shortlist',
                                                    'topic_workspace',
                                                ].includes(
                                                    form.data.source_type,
                                                )}
                                                onCheckedChange={(value) =>
                                                    toggleRun(
                                                        run.public_id,
                                                        value === true,
                                                    )
                                                }
                                            />
                                            <span className="min-w-0">
                                                <span
                                                    className="block truncate font-medium"
                                                    title={run.query_text}
                                                >
                                                    {run.query_text}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {run.video_count} videos ·{' '}
                                                    {formatDate(
                                                        run.completed_at,
                                                        timezone,
                                                    )}
                                                    {run.warning_count > 0
                                                        ? ` · ${run.warning_count} warning`
                                                        : ''}
                                                </span>
                                            </span>
                                            <MarketBadge
                                                market={run.market_key}
                                            />
                                        </label>
                                    ))}
                                </div>
                                <InputError
                                    message={form.errors.research_run_ids}
                                />
                            </fieldset>
                        )}

                        <fieldset className="space-y-3">
                            <legend className="font-medium">Video rows</legend>
                            <p className="text-sm text-muted-foreground">
                                Leave every row unselected to export all stored
                                video rows in this dataset, or select exact rows
                                to filter the export.
                            </p>
                            {selectableVideos.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                                    Select a dataset with completed video
                                    observations to filter rows.
                                </p>
                            ) : (
                                <div className="grid max-h-56 gap-2 overflow-y-auto rounded-xl border p-2">
                                    {selectableVideos.map((video) => (
                                        <label
                                            key={video.id}
                                            className="flex cursor-pointer items-center gap-3 rounded-lg p-2 has-checked:bg-primary/5"
                                        >
                                            <Checkbox
                                                checked={form.data.video_ids.includes(
                                                    video.id,
                                                )}
                                                onCheckedChange={(value) =>
                                                    toggleVideo(
                                                        video.id,
                                                        value === true,
                                                    )
                                                }
                                            />
                                            <span className="min-w-0 truncate text-sm">
                                                #{video.rank} · {video.title}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                            <InputError message={form.errors.video_ids} />
                        </fieldset>

                        <fieldset className="space-y-3">
                            <legend className="font-medium">File format</legend>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {(['xlsx', 'csv'] as const).map((format) => (
                                    <label
                                        key={format}
                                        className="cursor-pointer rounded-xl border p-4 has-checked:border-primary has-checked:bg-primary/5"
                                    >
                                        <input
                                            className="sr-only"
                                            type="radio"
                                            checked={
                                                form.data.format === format
                                            }
                                            onChange={() =>
                                                form.setData('format', format)
                                            }
                                        />
                                        <span className="flex items-center gap-2 font-semibold">
                                            <FileSpreadsheet className="size-4" />
                                            {format.toUpperCase()}
                                        </span>
                                        <span className="mt-1 block text-xs text-muted-foreground">
                                            {format === 'xlsx'
                                                ? 'Excel workbook with typed numeric cells.'
                                                : 'UTF-8 comma-separated file for broad compatibility.'}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <fieldset className="space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <legend className="font-medium">Columns</legend>
                                <label className="flex cursor-pointer items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={
                                            form.data.include_technical_details
                                        }
                                        onCheckedChange={(value) => {
                                            const include = value === true;
                                            form.setData({
                                                include_technical_details:
                                                    include,
                                                columns: include
                                                    ? builder.default_columns
                                                    : builder.standard_columns,
                                            });
                                        }}
                                    />
                                    Include technical details
                                </label>
                            </div>
                            <div className="grid gap-4 lg:grid-cols-2">
                                {Object.entries(builder.column_groups).map(
                                    ([group, columns]) => (
                                        <div
                                            key={group}
                                            className="rounded-xl border p-4"
                                        >
                                            <h3 className="mb-3 text-sm font-semibold">
                                                {group}
                                            </h3>
                                            <div className="grid gap-2">
                                                {columns.map((column) => (
                                                    <label
                                                        key={column.key}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        <Checkbox
                                                            checked={form.data.columns.includes(
                                                                column.key,
                                                            )}
                                                            disabled={
                                                                column.required
                                                            }
                                                            onCheckedChange={(
                                                                value,
                                                            ) =>
                                                                toggleColumn(
                                                                    column.key,
                                                                    value ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                        <span>
                                                            {column.label}
                                                            {column.required && (
                                                                <span className="ml-1 text-xs text-muted-foreground">
                                                                    required
                                                                </span>
                                                            )}
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                            <InputError message={form.errors.columns} />
                        </fieldset>

                        <div className="grid gap-4 rounded-xl border bg-muted/20 p-4 sm:grid-cols-4">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Runs
                                </p>
                                <p className="text-xl font-semibold">
                                    {selectedRuns.length}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Video rows
                                </p>
                                <p className="text-xl font-semibold">
                                    {selectedVideoRows}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Columns
                                </p>
                                <p className="text-xl font-semibold">
                                    {form.data.columns.length}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Expires after
                                </p>
                                <p className="text-xl font-semibold">
                                    {builder.expiry_days} days
                                </p>
                            </div>
                        </div>
                        {selectedWarnings > 0 && (
                            <Alert className="border-warning/40 bg-warning/10">
                                <AlertTriangle />
                                <AlertTitle>
                                    Partial-data warnings included
                                </AlertTitle>
                                <AlertDescription>
                                    {selectedWarnings} stored collection
                                    warning(s) will be preserved in the export
                                    rather than treated as zero.
                                </AlertDescription>
                            </Alert>
                        )}
                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm">
                            <Checkbox
                                checked={form.data.confirmed}
                                onCheckedChange={(value) =>
                                    form.setData('confirmed', value === true)
                                }
                            />
                            <span>
                                <span className="block font-medium">
                                    I confirm this exact stored dataset.
                                </span>
                                <span className="text-muted-foreground">
                                    The source, filters, selected rows, and
                                    snapshot versions will be frozen before the
                                    queued file is generated.
                                </span>
                            </span>
                        </label>
                        <InputError message={form.errors.confirmed} />
                    </CardContent>
                </Card>
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        size="lg"
                        disabled={
                            form.processing ||
                            selectedRuns.length === 0 ||
                            !form.data.confirmed
                        }
                    >
                        {form.processing ? <Spinner /> : <FileDown />}
                        {form.processing ? 'Queuing export…' : 'Create export'}
                    </Button>
                </div>
            </form>

            <Card>
                <CardHeader>
                    <CardTitle>Export jobs</CardTitle>
                    <CardDescription>
                        Queued jobs refresh automatically. Ready files remain
                        private and expire on the date shown.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {jobs.items.length === 0 ? (
                        <div className="py-10 text-center">
                            <FileDown className="mx-auto size-7 text-muted-foreground" />
                            <h3 className="mt-3 font-semibold">
                                No exports yet
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Your generated files will appear here.
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Contents</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead>Expiry / issue</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {jobs.items.map((job) => (
                                    <TableRow key={job.public_id}>
                                        <TableCell>
                                            {statusBadge(job)}
                                        </TableCell>
                                        <TableCell>
                                            <p className="font-medium">
                                                {job.format.toUpperCase()} ·{' '}
                                                {job.selection_label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {job.column_count} columns ·{' '}
                                                {formatBytes(job.size_bytes)}
                                            </p>
                                            {(job.status === 'queued' ||
                                                job.status ===
                                                    'processing') && (
                                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                                    <div className="h-full w-2/3 animate-pulse rounded-full bg-primary" />
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {formatDate(
                                                job.created_at,
                                                timezone,
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            {job.status === 'failed' ? (
                                                <span className="text-sm text-destructive">
                                                    {job.error_message ??
                                                        'Generation failed safely.'}
                                                </span>
                                            ) : job.expired ? (
                                                <span className="text-sm text-warning">
                                                    Expired; create a new
                                                    export.
                                                </span>
                                            ) : job.expires_at ? (
                                                formatDate(
                                                    job.expires_at,
                                                    timezone,
                                                )
                                            ) : (
                                                'Pending'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {job.can_download && (
                                                    <Button asChild size="sm">
                                                        <a
                                                            href={`/exports/${job.public_id}/download`}
                                                        >
                                                            <Download />
                                                            Download
                                                        </a>
                                                    </Button>
                                                )}
                                                {job.status === 'failed' && (
                                                    <Form
                                                        action={`/exports/${job.public_id}/retry`}
                                                        method="post"
                                                        disableWhileProcessing
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing ? (
                                                                    <Spinner />
                                                                ) : (
                                                                    <RefreshCw />
                                                                )}
                                                                Retry
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={`Delete ${job.format.toUpperCase()} export`}
                                                    onClick={() =>
                                                        setDeleting(job)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete export file?</DialogTitle>
                        <DialogDescription>
                            Delete this {deleting?.format.toUpperCase()} export
                            containing {deleting?.selection_label}? The
                            underlying research snapshots will not be changed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        {deleting && (
                            <Form
                                action={`/exports/${deleting.public_id}`}
                                method="delete"
                                disableWhileProcessing
                                onSuccess={() => setDeleting(null)}
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing ? <Spinner /> : <Trash2 />}
                                        Delete export
                                    </Button>
                                )}
                            </Form>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
