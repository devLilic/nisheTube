import { Head } from '@inertiajs/react';
import { Palette } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Palette className="size-5" aria-hidden="true" />
                        </div>
                        <div className="space-y-1">
                            <CardTitle>Workspace appearance</CardTitle>
                            <CardDescription>
                                Choose a light, dark, or system-matched theme
                                for this browser.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <AppearanceTabs className="w-full sm:w-auto" />
                    <p className="mt-4 text-sm leading-6 text-muted-foreground">
                        This preference is applied immediately and remembered on
                        this device.
                    </p>
                </CardContent>
            </Card>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
