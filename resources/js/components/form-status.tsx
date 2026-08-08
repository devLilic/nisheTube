import { CheckCircle2, Info } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

type FormStatusProps = {
    message?: string;
    title?: string;
    tone?: 'success' | 'info';
    className?: string;
};

export default function FormStatus({
    message,
    title,
    tone = 'success',
    className,
}: FormStatusProps) {
    if (!message) {
        return null;
    }

    const Icon = tone === 'success' ? CheckCircle2 : Info;

    return (
        <Alert
            className={cn(
                tone === 'success'
                    ? 'border-success/35 bg-success/10 text-success-foreground'
                    : 'border-info/35 bg-info/10 text-info-foreground',
                className,
            )}
        >
            <Icon aria-hidden="true" />
            {title && <AlertTitle>{title}</AlertTitle>}
            <AlertDescription className="text-current/80">
                {message}
            </AlertDescription>
        </Alert>
    );
}
