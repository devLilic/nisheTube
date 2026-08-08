import { AlertTriangle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export function PartialDataBanner({
    title = 'Some channel metrics are unavailable',
    description = 'Results remain useful, but hidden subscriber counts reduce confidence. Missing values are not treated as zero.',
}: {
    title?: string;
    description?: string;
}) {
    return (
        <Alert className="border-warning/40 bg-warning/10 text-warning-foreground">
            <AlertTriangle />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription className="text-warning-foreground/80">
                {description}
            </AlertDescription>
        </Alert>
    );
}
