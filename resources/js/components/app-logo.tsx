import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center">
                <AppLogoIcon className="size-8 drop-shadow-[0_5px_12px_rgba(79,70,229,0.28)]" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold tracking-tight">
                    {name}
                </span>
                <span className="truncate text-[10px] leading-tight text-sidebar-foreground/60">
                    Research workspace
                </span>
            </div>
        </>
    );
}
