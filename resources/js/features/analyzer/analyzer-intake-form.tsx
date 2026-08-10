import { useForm } from '@inertiajs/react';
import { LoaderCircle, ScanSearch } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function AnalyzerIntakeForm({
    prefill,
}: {
    prefill: {
        target_kind: 'video' | 'channel';
        target_reference: string;
        origin_kind: string;
        origin_reference: string | null;
        return_to: string | null;
    };
}) {
    const form = useForm({
        target_kind: prefill.target_kind,
        target_reference: prefill.target_reference,
        origin_kind: prefill.origin_kind,
        origin_reference: prefill.origin_reference,
        return_to: prefill.return_to,
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>Analyze a video or channel</CardTitle>
                <CardDescription>
                    Video and standalone author-channel entry use the same
                    immutable collection and channel metric services.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/analyzer');
                    }}
                    className="space-y-5"
                >
                    <div
                        className="grid grid-cols-2 gap-2"
                        role="group"
                        aria-label="Analyzer target"
                    >
                        {(['video', 'channel'] as const).map((kind) => (
                            <Button
                                key={kind}
                                type="button"
                                variant={
                                    form.data.target_kind === kind
                                        ? 'secondary'
                                        : 'outline'
                                }
                                onClick={() => {
                                    form.setData('target_kind', kind);
                                    form.clearErrors('target_reference');
                                }}
                                className="capitalize"
                            >
                                {kind}
                            </Button>
                        ))}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="target_reference">
                            YouTube {form.data.target_kind} URL or ID
                        </Label>
                        <Input
                            id="target_reference"
                            value={form.data.target_reference}
                            onChange={(event) =>
                                form.setData(
                                    'target_reference',
                                    event.target.value,
                                )
                            }
                            placeholder={
                                form.data.target_kind === 'video'
                                    ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
                                    : 'https://www.youtube.com/channel/UC...'
                            }
                            aria-invalid={Boolean(form.errors.target_reference)}
                            aria-describedby={
                                form.errors.target_reference
                                    ? 'target-reference-error'
                                    : 'target-reference-help'
                            }
                            autoFocus
                        />
                        {form.errors.target_reference ? (
                            <p
                                id="target-reference-error"
                                className="text-sm text-destructive"
                            >
                                {form.errors.target_reference}
                            </p>
                        ) : (
                            <p
                                id="target-reference-help"
                                className="text-xs leading-5 text-muted-foreground"
                            >
                                {form.data.target_kind === 'video'
                                    ? 'Supported: watch, youtu.be, Shorts, embed, and raw 11-character IDs.'
                                    : 'Supported: canonical /channel/ URLs and raw 24-character UC channel IDs.'}{' '}
                                Loading this form makes no provider request.
                            </p>
                        )}
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? (
                            <LoaderCircle className="animate-spin" />
                        ) : (
                            <ScanSearch />
                        )}
                        {form.processing ? 'Queueing analysis...' : 'Analyze'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
