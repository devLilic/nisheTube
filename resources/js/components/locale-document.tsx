import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import type { PropsWithChildren } from 'react';
import type { UiLocale } from '@/types';

type LocaleDocumentProps = PropsWithChildren<{
    initialLocale: UiLocale;
}>;

function syncDocumentLocale(locale: unknown): void {
    if (locale === 'ro' || locale === 'en') {
        document.documentElement.lang = locale;
    }
}

export default function LocaleDocument({
    children,
    initialLocale,
}: LocaleDocumentProps) {
    useEffect(() => {
        syncDocumentLocale(initialLocale);

        return router.on('navigate', (event) => {
            syncDocumentLocale(event.detail.page.props.locale);
        });
    }, [initialLocale]);

    return children;
}
