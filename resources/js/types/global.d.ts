import type { Auth } from '@/types/auth';
import type { UiLocale } from '@/types/localization';
import type { ResearchContext } from '@/types/navigation';
import type { CompletedRunNotifications } from '@/types/navigation';
import type { QuotaSummary } from '@/types/quota';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: UiLocale;
            fallbackLocale: 'en';
            supportedLocales: UiLocale[];
            auth: Auth;
            youtubeQuota: QuotaSummary | null;
            researchContext: ResearchContext | null;
            completedRunNotifications: CompletedRunNotifications | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
