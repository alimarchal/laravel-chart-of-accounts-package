import { router, useForm } from '@inertiajs/react';
import { Download, Paperclip, Trash2, Upload } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type AttachmentItem = {
    id: number;
    original_name: string;
    mime_type: string | null;
    size: number;
    description: string | null;
    uploaded_by: string | null;
    uploaded_at: string | null;
};

const fileSize = (bytes: number): string =>
    bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/**
 * Supporting documents of a journal entry: list with download, upload (when allowed) and removal (drafts only).
 */
export function AttachmentsPanel({
    entryId,
    attachments,
    canUpload,
    canRemove,
    rules,
}: {
    entryId: number;
    attachments: AttachmentItem[];
    canUpload: boolean;
    canRemove: boolean;
    rules: { max_size_kb: number; mimes: string[] };
}) {
    const form = useForm<{ files: File[]; description: string }>({
        files: [],
        description: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const fileError =
        errors.files ??
        Object.entries(errors).find(([key]) => key.startsWith('files.'))?.[1];

    const remove = (attachment: AttachmentItem) => {
        if (window.confirm(`Remove ${attachment.original_name}?`)) {
            router.delete(`/accounting/attachments/${attachment.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="rounded-lg border">
            <div className="flex items-center justify-between border-b p-4">
                <div className="flex items-center gap-2 font-medium">
                    <Paperclip className="size-4" /> Supporting documents
                    <span className="text-sm font-normal text-muted-foreground">
                        ({attachments.length})
                    </span>
                </div>
                {!canRemove && attachments.length ? (
                    <span className="text-xs text-muted-foreground">
                        Kept as evidence: posted entries cannot lose
                        attachments.
                    </span>
                ) : null}
            </div>
            {attachments.length ? (
                <ul className="divide-y">
                    {attachments.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex flex-wrap items-center gap-3 p-3 text-sm"
                        >
                            <a
                                href={`/accounting/attachments/${attachment.id}/download`}
                                className="inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                            >
                                <Download className="size-4" />
                                {attachment.original_name}
                            </a>
                            <span className="text-muted-foreground">
                                {fileSize(attachment.size)}
                                {attachment.uploaded_by
                                    ? ` · ${attachment.uploaded_by}`
                                    : ''}
                                {attachment.uploaded_at
                                    ? ` · ${new Date(attachment.uploaded_at).toLocaleString()}`
                                    : ''}
                            </span>
                            {attachment.description ? (
                                <span className="text-muted-foreground">
                                    — {attachment.description}
                                </span>
                            ) : null}
                            {canRemove ? (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="ml-auto"
                                    onClick={() => remove(attachment)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="p-4 text-sm text-muted-foreground">
                    No documents attached yet.
                </div>
            )}
            {canUpload ? (
                <form
                    className="flex flex-wrap items-end gap-3 border-t p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            `/accounting/journal-entries/${entryId}/attachments`,
                            {
                                preserveScroll: true,
                                forceFormData: true,
                                onSuccess: () => form.reset(),
                            },
                        );
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <Input
                            id="attachment_files"
                            type="file"
                            multiple
                            accept={rules.mimes.map((m) => `.${m}`).join(',')}
                            onChange={(event) =>
                                form.setData(
                                    'files',
                                    Array.from(event.target.files ?? []),
                                )
                            }
                        />
                        <span className="text-xs text-muted-foreground">
                            Up to {Math.round(rules.max_size_kb / 1024)} MB
                            each: {rules.mimes.join(', ')}
                        </span>
                        <InputError message={fileError} />
                    </div>
                    <Input
                        id="attachment_description"
                        placeholder="Description (optional)"
                        className="w-64"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                    <Button
                        type="submit"
                        disabled={form.processing || !form.data.files.length}
                    >
                        <Upload className="size-4" /> Attach
                    </Button>
                </form>
            ) : null}
        </div>
    );
}
