import { useForm } from '@inertiajs/react';
import { AlertTriangle, FileText, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/formatters';
import type { AnalyzerRun, AnalyzerTranscriptSegment } from '@/types';

const languages = [
    { value: 'en', label: 'English' },
    { value: 'ro', label: 'Romanian' },
    { value: 'ru', label: 'Russian' },
    { value: 'und', label: 'Unknown / mixed' },
] as const;

export function AnalyzerTranscriptSection({
    run,
    timezone,
}: {
    run: AnalyzerRun;
    timezone: string;
}) {
    const transcript = run.transcript;
    const document = transcript?.document;
    const [query, setQuery] = useState('');
    const [showForm, setShowForm] = useState(!document);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const form = useForm({
        transcript: '',
        language: 'en',
        rights_confirmed: false,
    });
    const deleteForm = useForm({});
    const filteredSegments = useMemo(() => {
        const normalized = query.trim().toLocaleLowerCase();

        if (!document || normalized === '') {
            return document?.segments ?? [];
        }

        return document.segments.filter((segment) =>
            segment.text.toLocaleLowerCase().includes(normalized),
        );
    }, [document, query]);

    if (!transcript || !transcript.can_manage) {
        return null;
    }

    const submit = () =>
        form.post(`/analyzer/runs/${run.public_id}/transcripts`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('transcript', 'rights_confirmed');
                setShowForm(false);
            },
        });
    const remove = () => {
        if (!document) {
            return;
        }

        deleteForm.delete(
            `/analyzer/runs/${run.public_id}/transcripts/${document.public_id}`,
            {
                preserveScroll: true,
                onSuccess: () => setConfirmDelete(false),
            },
        );
    };

    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="size-5" /> Transcript
                    </CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Optional user-provided evidence. Paste text copied for
                        this video; NisheTube never retrieves or scrapes a
                        transcript.
                    </p>
                </div>
                {document && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setShowForm((value) => !value)}
                        >
                            {showForm
                                ? 'Cancel replacement'
                                : 'Paste new revision'}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => setConfirmDelete(true)}
                        >
                            <Trash2 /> Delete current revision
                        </Button>
                    </div>
                )}
            </CardHeader>
            <CardContent className="space-y-5">
                {!document && (
                    <Alert>
                        <FileText />
                        <AlertTitle>No transcript provided</AlertTitle>
                        <AlertDescription>
                            Video and channel analysis remains complete without
                            a transcript. Add one only when it helps your
                            research.
                        </AlertDescription>
                    </Alert>
                )}

                {showForm && (
                    <form
                        className="space-y-4 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="transcript-language">
                                Language
                            </Label>
                            <select
                                id="transcript-language"
                                value={form.data.language}
                                onChange={(event) =>
                                    form.setData('language', event.target.value)
                                }
                                className="h-9 w-full rounded-md border bg-background px-3 text-sm sm:max-w-xs"
                            >
                                {languages.map((language) => (
                                    <option
                                        key={language.value}
                                        value={language.value}
                                    >
                                        {language.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="transcript-text">
                                Transcript text
                            </Label>
                            <Textarea
                                id="transcript-text"
                                rows={12}
                                maxLength={250000}
                                value={form.data.transcript}
                                onChange={(event) =>
                                    form.setData(
                                        'transcript',
                                        event.target.value,
                                    )
                                }
                                placeholder="Preferred: [0:00] Timestamped transcript… Plain text, SRT, and VTT are also accepted."
                                aria-invalid={Boolean(form.errors.transcript)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Timestamped text is best because each segment
                                can link back to its video position. Plain text
                                is stored as one untimed segment.
                            </p>
                            {form.errors.transcript && (
                                <p
                                    className="text-sm text-destructive"
                                    role="alert"
                                >
                                    {form.errors.transcript}
                                </p>
                            )}
                        </div>
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.rights_confirmed}
                                onChange={(event) =>
                                    form.setData(
                                        'rights_confirmed',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                I confirm that I have the right to use and store
                                this transcript for local research.
                            </span>
                        </label>
                        {form.errors.rights_confirmed && (
                            <p
                                className="text-sm text-destructive"
                                role="alert"
                            >
                                {form.errors.rights_confirmed}
                            </p>
                        )}
                        <Button
                            type="submit"
                            disabled={form.processing}
                            aria-busy={form.processing}
                        >
                            {form.processing
                                ? 'Saving transcript…'
                                : 'Save optional transcript'}
                        </Button>
                    </form>
                )}

                {document && (
                    <>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">User provided</Badge>
                            <Badge variant="outline" className="capitalize">
                                {document.input_format.replaceAll('_', ' ')}
                            </Badge>
                            <Badge variant="outline">
                                Language {document.language}
                            </Badge>
                            <Badge variant="outline">
                                {document.segment_count} segment(s)
                            </Badge>
                            <Badge variant="outline">
                                Revision {document.revision_count}
                            </Badge>
                        </div>
                        {document.warnings.map((warning) => (
                            <Alert key={warning}>
                                <AlertTriangle />
                                <AlertTitle>Transcript limitation</AlertTitle>
                                <AlertDescription>{warning}</AlertDescription>
                            </Alert>
                        ))}
                        <div className="space-y-2">
                            <Label htmlFor="transcript-search">
                                Search transcript
                            </Label>
                            <div className="relative sm:max-w-md">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    id="transcript-search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search exact transcript text"
                                    className="pl-9"
                                />
                            </div>
                        </div>
                        {filteredSegments.length === 0 ? (
                            <Alert>
                                <Search />
                                <AlertTitle>
                                    No matching transcript text
                                </AlertTitle>
                                <AlertDescription>
                                    Try another word or clear the search.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <ol
                                className="max-h-[34rem] space-y-3 overflow-y-auto pr-1"
                                aria-label="Transcript segments"
                            >
                                {filteredSegments.map((segment) => (
                                    <TranscriptSegmentRow
                                        key={segment.position}
                                        segment={segment}
                                        providerVideoId={run.target_provider_id}
                                    />
                                ))}
                            </ol>
                        )}
                        <p className="text-xs text-muted-foreground">
                            {document.character_count.toLocaleString()}{' '}
                            characters
                            {' · '}Provider {document.provider_version}
                            {' · '}Provided{' '}
                            {formatDate(document.provided_at, timezone)}
                            {' · '}Eligible for cleanup{' '}
                            {formatDate(document.retention_cutoff_at, timezone)}
                        </p>
                    </>
                )}
            </CardContent>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Delete current transcript revision?
                        </DialogTitle>
                        <DialogDescription>
                            This removes its stored text and segments. Video and
                            channel analysis will remain unchanged.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={remove}
                            disabled={deleteForm.processing}
                        >
                            {deleteForm.processing
                                ? 'Deleting…'
                                : 'Delete transcript'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

function TranscriptSegmentRow({
    segment,
    providerVideoId,
}: {
    segment: AnalyzerTranscriptSegment;
    providerVideoId: string;
}) {
    const seconds =
        segment.start_ms === null ? null : Math.floor(segment.start_ms / 1000);

    return (
        <li className="rounded-lg border p-4">
            <div className="flex items-start gap-3">
                {seconds === null ? (
                    <Badge variant="secondary">Untimed</Badge>
                ) : (
                    <a
                        href={`https://www.youtube.com/watch?v=${providerVideoId}&t=${seconds}s`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="rounded text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {formatTimestamp(seconds)}
                    </a>
                )}
                <p className="min-w-0 flex-1 text-sm break-words">
                    {segment.text}
                </p>
            </div>
        </li>
    );
}

function formatTimestamp(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return hours > 0
        ? `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
        : `${minutes}:${seconds.toString().padStart(2, '0')}`;
}
