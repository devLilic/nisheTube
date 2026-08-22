import { Head } from '@inertiajs/react';
import { Inbox, Info, Menu, Search, Sparkles } from 'lucide-react';
import {
    AppCanvas,
    AsyncSection,
    ChartFrame,
    ContentPanel,
    DataTableFrame,
    EvidenceRow,
    GlassCapsule,
    GlassCommandBar,
    GlassNavigationRail,
    GlassPanel,
    GlassPopover,
    GlassSheet,
    InspectorPanel,
    LoadingFixture,
    MetricTile,
} from '@/components/liquid-glass';
import { Button } from '@/components/ui/button';

const longRomanian =
    'Cum am construit un studio YouTube într-un apartament foarte mic: lumini, sunet, fundal și costurile reale după șase luni';
const longRussian =
    'Полный разбор компактной камеры для путешествий: стабилизация, автономность, перегрев и качество звука в реальных условиях';

export default function DesignSystem() {
    return (
        <>
            <Head title="Liquid Glass showcase" />
            <AppCanvas className="-m-4 min-h-[calc(100vh-4rem)] sm:-m-6">
                <div className="mx-auto grid max-w-[1680px] gap-6 lg:grid-cols-[248px_minmax(0,1fr)]">
                    <GlassNavigationRail className="hidden h-fit lg:block">
                        <p className="px-3 text-xs font-bold tracking-widest text-[var(--lg-ink-muted)] uppercase">
                            NisheTube
                        </p>
                        {[
                            'Discover',
                            'Validate',
                            'Analyze',
                            'Organize',
                            'Manage',
                        ].map((group, index) => (
                            <a
                                key={group}
                                href="#showcase"
                                aria-current={index === 0 ? 'page' : undefined}
                                className="mt-2 flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold hover:bg-primary/10 focus-visible:bg-primary/10"
                            >
                                {group}
                            </a>
                        ))}
                    </GlassNavigationRail>
                    <div className="min-w-0 space-y-6">
                        <GlassCommandBar className="sticky top-4 z-10 flex min-h-14 items-center gap-3">
                            <GlassSheet
                                trigger={
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="min-h-11 min-w-11 lg:hidden"
                                        aria-label="Open navigation"
                                    >
                                        <Menu />
                                    </Button>
                                }
                                title="Research navigation"
                                description="All destinations remain available on mobile."
                            >
                                <nav className="space-y-1">
                                    {[
                                        'Discover',
                                        'Validate',
                                        'Analyze',
                                        'Organize',
                                        'Manage',
                                    ].map((group) => (
                                        <a
                                            key={group}
                                            href="#showcase"
                                            className="flex min-h-11 items-center rounded-xl px-3 font-semibold hover:bg-primary/10"
                                        >
                                            {group}
                                        </a>
                                    ))}
                                </nav>
                            </GlassSheet>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-bold">
                                    Liquid Glass system
                                </p>
                                <p className="hidden text-xs text-[var(--lg-ink-muted)] sm:block">
                                    Internal local fixture board
                                </p>
                            </div>
                            <Button variant="outline" className="min-h-11">
                                <Search />
                                Search
                            </Button>
                            <GlassCapsule tone="info">Quota 8/10</GlassCapsule>
                        </GlassCommandBar>
                        <header
                            id="showcase"
                            className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]"
                        >
                            <div>
                                <p className="text-sm font-semibold text-primary">
                                    RDSN-02 · fixture data only
                                </p>
                                <h1 className="mt-2 max-w-3xl text-[32px] leading-[38px] font-bold tracking-tight sm:text-4xl">
                                    Analizează oportunitatea nișei înainte de a
                                    consuma cota YouTube
                                </h1>
                                <p className="mt-3 max-w-3xl text-[15px] leading-[22px] text-[var(--lg-ink-muted)]">
                                    Reusable materials remain calm around exact
                                    evidence. This local page is available only
                                    to authenticated, verified users in the
                                    local environment.
                                </p>
                            </div>
                            <GlassPanel>
                                <p className="font-semibold">
                                    Material hierarchy
                                </p>
                                <p className="mt-1 text-sm leading-6 text-[var(--lg-ink-muted)]">
                                    Three blurred layers maximum: rail, command
                                    bar, and this contextual panel.
                                </p>
                            </GlassPanel>
                        </header>
                        <section aria-labelledby="metrics-title">
                            <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <h2
                                        id="metrics-title"
                                        className="text-lg font-bold"
                                    >
                                        MetricTile
                                    </h2>
                                    <p className="text-sm text-[var(--lg-ink-muted)]">
                                        Exact values, basis, and confidence stay
                                        together.
                                    </p>
                                </div>
                                <GlassPopover
                                    title="Opaque fallback"
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                        >
                                            Material notes
                                        </Button>
                                    }
                                >
                                    When blur is unavailable or contrast is
                                    stronger, every glass tier becomes opaque
                                    with its border and shadow retained.
                                </GlassPopover>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <MetricTile
                                    label="Observed views"
                                    value="2,481,309"
                                    basis="25 returned videos"
                                    confidence="Moderate confidence"
                                    delta="+12.4%"
                                />
                                <MetricTile
                                    label="Median views / day"
                                    value="8,420"
                                    basis="Collected Aug 21, 2026"
                                    confidence="Observed sample"
                                />
                                <MetricTile
                                    label="Format coverage"
                                    value="18 / 25"
                                    basis="7 videos not classified"
                                    confidence="Partial data"
                                />
                                <MetricTile
                                    label="Unavailable example"
                                    value="Unavailable"
                                    basis="Not collected"
                                    unavailableReason="Not collected: enrichment was not requested."
                                />
                            </div>
                        </section>
                        <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                            <DataTableFrame
                                caption="DataTableFrame — evidence fixtures"
                                state="partial"
                            >
                                <table className="w-full min-w-[42rem] text-sm">
                                    <caption className="sr-only">
                                        Fixture evidence with exact observed
                                        values
                                    </caption>
                                    <thead className="border-b border-[var(--lg-border-soft)] text-left text-xs text-[var(--lg-ink-muted)]">
                                        <tr>
                                            <th className="p-4">Evidence</th>
                                            <th className="p-4 text-right">
                                                Views
                                            </th>
                                            <th className="p-4 text-right">
                                                Confidence
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td className="p-0" colSpan={3}>
                                                <EvidenceRow
                                                    title={longRomanian}
                                                    source="Video fixture · Romania"
                                                    timestamp="Aug 21, 2026"
                                                    metrics={[
                                                        {
                                                            label: 'Views',
                                                            value: '184,220',
                                                        },
                                                        {
                                                            label: 'Score',
                                                            value: '64',
                                                        },
                                                    ]}
                                                    action={
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="min-h-11"
                                                        >
                                                            Inspect
                                                        </Button>
                                                    }
                                                />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="p-0" colSpan={3}>
                                                <EvidenceRow
                                                    title={longRussian}
                                                    source="Video fixture · Russia"
                                                    timestamp="Aug 20, 2026"
                                                    metrics={[
                                                        {
                                                            label: 'Views',
                                                            value: '92,400',
                                                        },
                                                        {
                                                            label: 'Score',
                                                            value: '—',
                                                        },
                                                    ]}
                                                    action={
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="min-h-11"
                                                        >
                                                            Inspect
                                                        </Button>
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </DataTableFrame>
                            <InspectorPanel
                                title="InspectorPanel"
                                description="Formula, source, coverage, and limitation context."
                            >
                                <dl className="space-y-2">
                                    <div>
                                        <dt className="text-xs text-[var(--lg-ink-muted)]">
                                            Source
                                        </dt>
                                        <dd>Returned YouTube fixtures</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-[var(--lg-ink-muted)]">
                                            Limitation
                                        </dt>
                                        <dd>
                                            Observed demand is not search
                                            volume.
                                        </dd>
                                    </div>
                                </dl>
                            </InspectorPanel>
                        </section>
                        <section className="grid gap-6 xl:grid-cols-2">
                            <ChartFrame
                                title="ChartFrame"
                                source="Fixture collection"
                                sampleSize="25 videos"
                                exactValues={
                                    <ol className="mt-3 list-decimal space-y-1 pl-5 text-sm">
                                        <li>Aug 19 — 7,840 views/day</li>
                                        <li>Aug 20 — 8,112 views/day</li>
                                        <li>Aug 21 — 8,420 views/day</li>
                                    </ol>
                                }
                            >
                                <div className="flex h-36 items-end gap-3 border-b border-l border-[var(--lg-border-strong)] p-4">
                                    <span
                                        className="w-1/3 bg-primary/35"
                                        style={{ height: '55%' }}
                                    />
                                    <span
                                        className="w-1/3 bg-primary/55"
                                        style={{ height: '70%' }}
                                    />
                                    <span
                                        className="w-1/3 bg-primary/80"
                                        style={{ height: '82%' }}
                                    />
                                </div>
                            </ChartFrame>
                            <div className="space-y-3">
                                <AsyncSection
                                    state="idle"
                                    title="AsyncSection — comments"
                                    description="No comment collection requested. This is the idle/empty state."
                                />
                                <AsyncSection
                                    state="processing"
                                    title="AsyncSection — thumbnails"
                                    description="Processing 18 of 50 thumbnails. It does not consume YouTube quota."
                                />
                                <AsyncSection
                                    state="failed"
                                    title="AsyncSection — recovery"
                                    description="Existing analysis is unchanged. Retry only the failed collection."
                                />
                            </div>
                        </section>
                        <section aria-labelledby="state-title">
                            <h2 id="state-title" className="text-lg font-bold">
                                Loading, empty, partial, success, and error
                                grammar
                            </h2>
                            <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                                <LoadingFixture />
                                <ContentPanel className="p-4">
                                    <Inbox className="size-5 text-[var(--lg-ink-muted)]" />
                                    <h3 className="mt-3 font-semibold">
                                        Empty
                                    </h3>
                                    <p className="mt-1 text-sm text-[var(--lg-ink-muted)]">
                                        No observations match these filters.
                                    </p>
                                </ContentPanel>
                                <ContentPanel className="border-warning/50 p-4">
                                    <Info className="size-5 text-warning-foreground" />
                                    <h3 className="mt-3 font-semibold">
                                        Partial
                                    </h3>
                                    <p className="mt-1 text-sm text-[var(--lg-ink-muted)]">
                                        7 of 25 values lack a format
                                        classification.
                                    </p>
                                </ContentPanel>
                                <ContentPanel className="p-4">
                                    <Sparkles className="size-5 text-success-foreground" />
                                    <h3 className="mt-3 font-semibold">
                                        Success
                                    </h3>
                                    <p className="mt-1 text-sm text-[var(--lg-ink-muted)]">
                                        Exact values and provenance are visible.
                                    </p>
                                </ContentPanel>
                                <ContentPanel tone="critical" className="p-4">
                                    <h3 className="font-semibold">Error</h3>
                                    <p className="mt-1 text-sm text-[var(--lg-ink-muted)]">
                                        A local failure never replaces retained
                                        evidence.
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mt-3 min-h-11"
                                    >
                                        Retry
                                    </Button>
                                </ContentPanel>
                            </div>
                        </section>
                        <footer className="text-xs leading-5 text-[var(--lg-ink-muted)]">
                            Responsive review fixtures: 1440px desktop rail,
                            1024px compact rail, and 390px full navigation
                            sheet. Reduced motion, forced colors, and opaque
                            fallback preserve the same meaning.
                        </footer>
                    </div>
                </div>
            </AppCanvas>
        </>
    );
}

DesignSystem.layout = {
    breadcrumbs: [{ title: 'Liquid Glass showcase', href: '/design-system' }],
};
