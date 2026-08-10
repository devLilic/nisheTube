import { Link, useForm } from '@inertiajs/react';
import { PanelsTopLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { TopicEvidenceRole, TopicEvidenceType } from '@/types';

export type WorkspaceOption = {
    public_id: string;
    name: string;
    market_key: string;
};

export function WorkspaceHandoff({
    workspaces,
    targetType,
    targetReference,
    role = 'evidence',
}: {
    workspaces: WorkspaceOption[];
    targetType: TopicEvidenceType;
    targetReference: string;
    role?: TopicEvidenceRole;
}) {
    const form = useForm({
        target_type: targetType,
        target_reference: targetReference,
        evidence_role: role,
        note: '',
        workspace: workspaces[0]?.public_id ?? '',
    });

    if (workspaces.length === 0) {
        return (
            <Button asChild size="sm" variant="outline">
                <Link href="/topics">
                    <PanelsTopLeft /> Create workspace
                </Link>
            </Button>
        );
    }

    const submit = () => {
        if (!form.data.workspace) {
            return;
        }

        form.post(`/topics/${form.data.workspace}/evidence`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="flex min-w-0 flex-wrap gap-2">
            <label
                className="sr-only"
                htmlFor={`workspace-${targetType}-${targetReference}`}
            >
                Topic Workspace
            </label>
            <select
                id={`workspace-${targetType}-${targetReference}`}
                value={form.data.workspace}
                onChange={(event) =>
                    form.setData('workspace', event.target.value)
                }
                className="h-9 max-w-52 min-w-36 rounded-md border bg-background px-2 text-xs"
            >
                {workspaces.map((workspace) => (
                    <option
                        key={workspace.public_id}
                        value={workspace.public_id}
                    >
                        {workspace.name} · {workspace.market_key}
                    </option>
                ))}
            </select>
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={submit}
                disabled={form.processing}
            >
                <PanelsTopLeft />{' '}
                {form.processing ? 'Adding…' : 'Add to workspace'}
            </Button>
            {form.errors.target_reference && (
                <p className="w-full text-xs text-destructive">
                    {form.errors.target_reference}
                </p>
            )}
        </div>
    );
}
