import { router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    FolderKanban,
    Globe2,
    LoaderCircle,
    PanelsTopLeft,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ResearchContextOption } from '@/types';

const NONE = '__none__';

type ContextKey = 'market' | 'project' | 'workspace';

const selectedValue = (option: ResearchContextOption | null) =>
    option?.value ?? NONE;

export function ResearchContextControls() {
    const context = usePage().props.researchContext;
    const [state, setState] = useState<'idle' | 'saving' | 'saved' | 'error'>(
        'idle',
    );

    useEffect(() => {
        if (state !== 'saved') {
            return;
        }

        const timeout = window.setTimeout(() => setState('idle'), 1800);

        return () => window.clearTimeout(timeout);
    }, [state]);

    if (!context) {
        return null;
    }

    const update = (changed: ContextKey, value: string) => {
        const next = value === NONE ? null : value;
        const payload = {
            changed,
            market_key: context.selection.market?.value ?? null,
            project: context.selection.project?.value ?? null,
            workspace: context.selection.workspace?.value ?? null,
        };

        if (changed === 'market') {
            payload.market_key = next;
        }

        if (changed === 'project') {
            payload.project = next;
        }

        if (changed === 'workspace') {
            payload.workspace = next;
        }

        router.put('/research-context', payload, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setState('saving'),
            onSuccess: () => setState('saved'),
            onError: () => setState('error'),
        });
    };

    return (
        <div className="order-3 flex w-full min-w-0 items-center gap-2 overflow-x-auto pb-0.5 xl:order-none xl:w-auto xl:overflow-visible">
            <ContextSelect
                label="Market"
                icon={<Globe2 />}
                value={selectedValue(context.selection.market)}
                options={context.options.markets}
                placeholder="Select market"
                onChange={(value) => update('market', value)}
                disabled={state === 'saving'}
            />
            <ContextSelect
                label="Project"
                icon={<FolderKanban />}
                value={selectedValue(context.selection.project)}
                options={context.options.projects}
                placeholder="No project"
                allowNone
                onChange={(value) => update('project', value)}
                disabled={state === 'saving'}
            />
            <ContextSelect
                label="Workspace"
                icon={<PanelsTopLeft />}
                value={selectedValue(context.selection.workspace)}
                options={context.options.workspaces}
                placeholder="No workspace"
                allowNone
                onChange={(value) => update('workspace', value)}
                disabled={state === 'saving'}
            />
            <ContextStatus state={state} notice={context.notice} />
        </div>
    );
}

function ContextSelect({
    label,
    icon,
    value,
    options,
    placeholder,
    allowNone = false,
    onChange,
    disabled,
}: {
    label: string;
    icon: React.ReactNode;
    value: string;
    options: ResearchContextOption[];
    placeholder: string;
    allowNone?: boolean;
    onChange: (value: string) => void;
    disabled: boolean;
}) {
    return (
        <Select value={value} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger
                size="sm"
                aria-label={`${label}: ${options.find((item) => item.value === value)?.label ?? placeholder}`}
                className="max-w-44 min-w-32 bg-card sm:max-w-52"
            >
                {icon}
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent align="start" className="max-w-80">
                <SelectGroup>
                    <SelectLabel>{label}</SelectLabel>
                    {allowNone && (
                        <SelectItem value={NONE}>{placeholder}</SelectItem>
                    )}
                    {!allowNone && options.length === 0 && (
                        <SelectItem value={NONE} disabled>
                            No markets available
                        </SelectItem>
                    )}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            <span
                                className="block max-w-64 truncate"
                                title={option.label}
                            >
                                {option.label}
                            </span>
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}

function ContextStatus({
    state,
    notice,
}: {
    state: 'idle' | 'saving' | 'saved' | 'error';
    notice: string | null;
}) {
    const message =
        state === 'saving'
            ? 'Saving research context.'
            : state === 'saved'
              ? 'Research context saved.'
              : state === 'error'
                ? 'Research context could not be saved. Your previous selection is still active.'
                : notice;

    if (!message) {
        return null;
    }

    return (
        <p
            className="flex max-w-56 shrink-0 items-center gap-1 text-xs text-muted-foreground"
            role="status"
            aria-live="polite"
            title={message}
        >
            {state === 'saving' && (
                <LoaderCircle className="size-3.5 animate-spin" />
            )}
            {state === 'saved' && <Check className="size-3.5" />}
            {(state === 'error' || notice) && (
                <AlertTriangle className="size-3.5 text-amber-600" />
            )}
            <span className="truncate">{message}</span>
        </p>
    );
}
