import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, Globe2, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';

const benefits = [
    {
        icon: Globe2,
        title: 'Three focused markets',
        description:
            'Research Global/English, Romania/Romanian, and Russia/Russian results in one local workspace.',
    },
    {
        icon: BarChart3,
        title: 'Stored evidence',
        description:
            'Keep timestamped runs, metrics, and decisions so you can compare what was actually returned.',
    },
    {
        icon: ShieldCheck,
        title: 'Private by design',
        description:
            'Each local account keeps its own projects, saved findings, and research history.',
    },
];

export default function Welcome() {
    return (
        <>
            <Head title="YouTube niche research with stored evidence" />

            <div className="min-h-svh bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-3 rounded-lg font-semibold tracking-tight focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <AppLogoIcon className="size-9" />
                        <span>NisheTube</span>
                    </Link>

                    <nav
                        aria-label="Authentication actions"
                        className="flex items-center gap-2"
                    >
                        <Link
                            href={login()}
                            className={cn(buttonVariants({ variant: 'ghost' }))}
                            data-test="landing-sign-in"
                        >
                            Sign in
                        </Link>
                        <Link
                            href={register()}
                            className={cn(buttonVariants())}
                            data-test="landing-register"
                        >
                            Create account
                        </Link>
                    </nav>
                </header>

                <main>
                    <section className="mx-auto grid w-full max-w-6xl gap-12 px-5 pt-14 pb-20 sm:px-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center lg:pt-24">
                        <div>
                            <p className="text-sm font-medium text-primary">
                                Local YouTube niche research
                            </p>
                            <h1 className="mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                Find niches with stored evidence, not guesses.
                            </h1>
                            <p className="mt-6 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                                NisheTube turns returned YouTube videos and
                                channels into a private research record. It
                                measures observed demand from those results; it
                                does not measure YouTube search volume.
                            </p>

                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={register()}
                                    className={cn(
                                        buttonVariants({ size: 'lg' }),
                                    )}
                                    data-test="landing-hero-register"
                                >
                                    Start private research
                                    <ArrowRight aria-hidden="true" />
                                </Link>
                                <Link
                                    href={login()}
                                    className={cn(
                                        buttonVariants({
                                            variant: 'outline',
                                            size: 'lg',
                                        }),
                                    )}
                                >
                                    Sign in to your workspace
                                </Link>
                            </div>
                        </div>

                        <aside
                            className="rounded-2xl border bg-card p-6 shadow-sm sm:p-8"
                            aria-label="What NisheTube stores"
                        >
                            <p className="text-sm font-medium text-primary">
                                What you keep
                            </p>
                            <ul className="mt-5 space-y-4 text-sm leading-6 text-muted-foreground">
                                <li className="border-b pb-4">
                                    A timestamped record of each research run.
                                </li>
                                <li className="border-b pb-4">
                                    The returned videos, channels, and metrics
                                    behind your conclusions.
                                </li>
                                <li>
                                    Your saved projects and decisions, available
                                    only to your local account.
                                </li>
                            </ul>
                        </aside>
                    </section>

                    <section
                        className="border-y bg-muted/40"
                        aria-labelledby="landing-benefits"
                    >
                        <div className="mx-auto w-full max-w-6xl px-5 py-14 sm:px-8">
                            <h2
                                id="landing-benefits"
                                className="text-2xl font-semibold tracking-tight"
                            >
                                Research with context you can revisit
                            </h2>
                            <div className="mt-8 grid gap-4 md:grid-cols-3">
                                {benefits.map(
                                    ({ icon: Icon, title, description }) => (
                                        <article
                                            key={title}
                                            className="rounded-xl border bg-background p-5"
                                        >
                                            <Icon
                                                className="size-5 text-primary"
                                                aria-hidden="true"
                                            />
                                            <h3 className="mt-4 font-medium">
                                                {title}
                                            </h3>
                                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                                {description}
                                            </p>
                                        </article>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="mx-auto w-full max-w-6xl px-5 py-8 text-sm text-muted-foreground sm:px-8">
                    NisheTube runs locally. Account data stays in this
                    installation.
                </footer>
            </div>
        </>
    );
}
