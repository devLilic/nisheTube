import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useState } from 'react';
import { translate } from '@/i18n';
import { cn } from '@/lib/utils';
import type { UiLocale } from '@/types';

type LocaleSelectorProps = {
    className?: string;
    compact?: boolean;
};

export default function LocaleSelector({
    className,
    compact = false,
}: LocaleSelectorProps) {
    const { locale, supportedLocales } = usePage().props;
    const [processing, setProcessing] = useState(false);
    const label = translate(locale, 'locale.label');

    return (
        <label
            className={cn(
                'inline-flex min-w-0 items-center gap-2 text-sm font-medium',
                className,
            )}
        >
            <Languages className="size-4 shrink-0" aria-hidden="true" />
            {!compact && <span>{label}</span>}
            <span className="sr-only">{label}</span>
            <select
                value={locale}
                disabled={processing}
                aria-label={label}
                className="h-9 rounded-md border border-input bg-background px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-wait disabled:opacity-60"
                onChange={(event) => {
                    const uiLocale = event.target.value as UiLocale;

                    router.put(
                        '/locale',
                        { ui_locale: uiLocale },
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                        },
                    );
                }}
            >
                {supportedLocales.map((supportedLocale) => (
                    <option key={supportedLocale} value={supportedLocale}>
                        {translate(locale, `locale.${supportedLocale}`)}
                    </option>
                ))}
            </select>
            {processing && (
                <span className="sr-only" role="status">
                    {translate(locale, 'locale.updating')}
                </span>
            )}
        </label>
    );
}
