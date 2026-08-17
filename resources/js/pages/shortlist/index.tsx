import { Head, Link } from '@inertiajs/react';
import { ListChecks, Search, TriangleAlert } from 'lucide-react';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
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
import { create } from '@/routes/research';
import { show } from '@/routes/research/runs';

type Item = {
    public_id: string;
    query_text: string;
    score: {
        overall_score: number;
        confidence_score: number;
        formula_version: string;
    } | null;
    profitability_fit: { fit_score: number; formula_version: string } | null;
};
type Props = {
    shortlist: {
        items: Item[];
        selected: Item[];
        selection: { count: number; state: string; message: string };
    };
};

export default function ShortlistIndex({ shortlist }: Props) {
    const selected = new Set(shortlist.selected.map((item) => item.public_id));

    return (
        <>
            <Head title="Shortlist" />
            <PageContainer>
                <PageHeader
                    title="Shortlist"
                    description="Compare two to five saved Research decisions using frozen local evidence only."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Search aria-hidden="true" /> New research
                            </Link>
                        </Button>
                    }
                />
                <Alert
                    className={
                        shortlist.selection.state === 'ready'
                            ? 'border-success/35 bg-success/8 text-success-foreground'
                            : 'border-warning/40 bg-warning/10 text-warning-foreground'
                    }
                >
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>
                        {shortlist.selection.state === 'ready'
                            ? 'Compatible comparison ready'
                            : 'Comparison needs attention'}
                    </AlertTitle>
                    <AlertDescription>
                        {shortlist.selection.message}
                    </AlertDescription>
                </Alert>
                {shortlist.items.length === 0 ? (
                    <Card>
                        <CardContent className="flex min-h-64 flex-col items-center justify-center text-center">
                            <ListChecks
                                className="size-8 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="mt-3 font-medium">
                                Your shortlist is empty
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Open a Research result and use Add to shortlist.
                                Saved items remain private to your account.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <form
                        action="/shortlist"
                        method="get"
                        className="space-y-4"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Choose saved Research runs
                                </CardTitle>
                                <CardDescription>
                                    Select two to five. This view never calls
                                    YouTube or recalculates history.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {shortlist.items.map((item) => (
                                    <label
                                        key={item.public_id}
                                        className="flex items-center justify-between gap-4 rounded-lg border p-4"
                                    >
                                        <span className="flex min-w-0 items-center gap-3">
                                            <input
                                                type="checkbox"
                                                name="runs[]"
                                                value={item.public_id}
                                                defaultChecked={selected.has(
                                                    item.public_id,
                                                )}
                                            />
                                            <span>
                                                <span className="block font-medium">
                                                    {item.query_text}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {item.score
                                                        ? `${item.score.formula_version} · ${item.score.overall_score.toFixed(1)} opportunity`
                                                        : 'Legacy / score unavailable'}
                                                </span>
                                            </span>
                                        </span>
                                        <Link
                                            href={show(item.public_id)}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            View
                                        </Link>
                                    </label>
                                ))}
                            </CardContent>
                        </Card>
                        <Button type="submit">
                            Compare selected ({shortlist.selection.count})
                        </Button>
                    </form>
                )}
                {shortlist.selected.length >= 2 && (
                    <section aria-labelledby="frozen-comparison">
                        <h2
                            id="frozen-comparison"
                            className="mb-3 text-lg font-semibold"
                        >
                            Frozen comparison
                        </h2>
                        <div className="grid gap-3 lg:grid-cols-2">
                            {shortlist.selected.map((item) => (
                                <Card key={item.public_id}>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            {item.query_text}
                                        </CardTitle>
                                        <CardDescription>
                                            {item.score?.formula_version ??
                                                'Score unavailable'}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-2 text-sm">
                                        <p>
                                            Opportunity:{' '}
                                            <strong>
                                                {item.score
                                                    ? `${item.score.overall_score.toFixed(1)} / 100`
                                                    : 'Unavailable'}
                                            </strong>
                                        </p>
                                        <p>
                                            Confidence:{' '}
                                            <strong>
                                                {item.score
                                                    ? `${item.score.confidence_score.toFixed(1)} / 100`
                                                    : 'Unavailable'}
                                            </strong>
                                        </p>
                                        <p>
                                            Estimated profitability fit:{' '}
                                            <strong>
                                                {item.profitability_fit
                                                    ? `${item.profitability_fit.fit_score.toFixed(1)} / 100`
                                                    : 'Unavailable for this legacy result'}
                                            </strong>
                                        </p>
                                        <Badge variant="outline">
                                            Stored evidence
                                        </Badge>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </section>
                )}
            </PageContainer>
        </>
    );
}
