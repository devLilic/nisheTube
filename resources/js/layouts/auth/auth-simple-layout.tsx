import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { BarChart3, Globe2, History, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import LocaleSelector from '@/components/locale-selector';
import { Badge } from '@/components/ui/badge';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const researchBenefits: { icon: LucideIcon; label: string }[] = [
    { icon: Globe2, label: 'Three research markets' },
    { icon: BarChart3, label: 'Explainable opportunity signals' },
    { icon: History, label: 'Timestamped research history' },
    { icon: ShieldCheck, label: 'Private local accounts' },
];

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <main className="relative grid min-h-svh bg-background lg:grid-cols-[minmax(0,0.92fr)_minmax(32rem,1.08fr)]">
            <LocaleSelector className="absolute top-5 right-5 z-20" compact />
            <section className="relative hidden overflow-hidden bg-sidebar px-12 py-10 text-sidebar-foreground lg:flex lg:flex-col">
                <div className="absolute -top-24 -left-20 size-80 rounded-full bg-sidebar-primary/20 blur-3xl" />
                <div className="absolute right-0 bottom-0 size-96 translate-x-1/3 translate-y-1/3 rounded-full bg-info/15 blur-3xl" />

                <Link
                    href={home()}
                    className="relative z-10 flex w-fit items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                >
                    <AppLogoIcon className="size-10 text-sidebar-primary" />
                    <div>
                        <p className="text-base font-semibold tracking-tight">
                            {name}
                        </p>
                        <p className="text-xs text-sidebar-foreground/60">
                            YouTube niche research
                        </p>
                    </div>
                </Link>

                <div className="relative z-10 my-auto max-w-xl py-16">
                    <Badge className="mb-6 border-sidebar-border bg-sidebar-accent text-sidebar-accent-foreground">
                        Local research workspace
                    </Badge>
                    <h2 className="max-w-lg text-4xl leading-tight font-semibold tracking-tight text-balance xl:text-5xl">
                        Find promising niches with evidence, not guesswork.
                    </h2>
                    <p className="mt-5 max-w-lg text-base leading-7 text-sidebar-foreground/70">
                        Explore market-specific YouTube results, compare
                        signals, and keep a private history of every research
                        run.
                    </p>

                    <div className="mt-10 grid max-w-lg grid-cols-2 gap-3 text-sm">
                        {researchBenefits.map(({ icon: Icon, label }) => (
                            <div
                                key={label}
                                className="flex items-center gap-3 rounded-xl border border-sidebar-border bg-sidebar-accent/55 px-4 py-3 text-sidebar-foreground/85"
                            >
                                <Icon
                                    className="size-4 text-sidebar-primary"
                                    aria-hidden="true"
                                />
                                <span>{label}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <p className="relative z-10 text-xs text-sidebar-foreground/50">
                    Your research stays in this local NisheTube installation.
                </p>
            </section>

            <section className="flex min-h-svh items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                <div className="w-full max-w-md">
                    <div className="mb-8 flex items-center justify-between lg:hidden">
                        <Link
                            href={home()}
                            className="flex items-center gap-3 rounded-lg font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <AppLogoIcon className="size-9 text-primary" />
                            <span>{name}</span>
                        </Link>
                        <Badge variant="outline">Local app</Badge>
                    </div>

                    <div className="rounded-2xl border bg-card p-6 shadow-sm sm:p-8">
                        <div className="mb-7 space-y-2">
                            <h1 className="text-2xl font-semibold tracking-tight text-balance">
                                {title}
                            </h1>
                            <p className="text-sm leading-6 text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>

                    <p className="mt-6 text-center text-xs leading-5 text-muted-foreground">
                        NisheTube is a local research tool. Account data is
                        stored in this installation.
                    </p>
                </div>
            </section>
        </main>
    );
}
