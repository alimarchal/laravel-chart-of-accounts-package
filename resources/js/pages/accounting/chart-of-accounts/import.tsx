import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, FileUp, Upload, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAccounting } from '@/lib/accounting';

type PreviewRow = {
    line: number;
    account_code: string;
    account_name: string;
    action: 'create' | 'update' | 'unchanged' | 'error';
    changes: Record<string, [unknown, unknown]>;
    errors: string[];
};

type Preview = {
    rows: PreviewRow[];
    summary: {
        create: number;
        update: number;
        unchanged: number;
        error: number;
    };
    token: string | null;
    mode: 'upsert' | 'create';
    filename: string;
};

type Props = {
    preview: Preview | null;
    columns: string[];
};

const actionStyles: Record<PreviewRow['action'], string> = {
    create: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    update: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
    unchanged: 'bg-muted text-muted-foreground',
    error: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

const show = (value: unknown) =>
    (typeof value === 'string' && value !== '') ||
    typeof value === 'number' ||
    typeof value === 'boolean'
        ? String(value)
        : '—';

export default function ChartOfAccountsImport({ preview, columns }: Props) {
    const { flash } = useAccounting();
    const form = useForm<{ file: File | null; mode: 'upsert' | 'create' }>({
        file: null,
        mode: preview?.mode ?? 'upsert',
    });
    // The token belongs to the preview on screen: a later upload replaces it, so it is never kept in form state.
    const [importing, setImporting] = useState(false);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/accounting/chart-of-accounts/import/preview', {
            forceFormData: true,
        });
    };

    const importNow = () => {
        if (!preview?.token) {
            return;
        }

        router.post(
            '/accounting/chart-of-accounts/import',
            { token: preview.token },
            {
                onStart: () => setImporting(true),
                onFinish: () => setImporting(false),
            },
        );
    };

    const toImport = preview
        ? preview.summary.create + preview.summary.update
        : 0;

    return (
        <>
            <Head title="Import Chart of Accounts" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Import Chart of Accounts"
                        description="Add or update accounts from a CSV or Excel file. You see every change before anything is saved."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="gap-2">
                            <a href="/accounting/chart-of-accounts/import/template/xlsx">
                                <Download className="size-4" />
                                <span>Template (Excel)</span>
                            </a>
                        </Button>
                        <Button asChild variant="outline" className="gap-2">
                            <a href="/accounting/chart-of-accounts/export/xlsx">
                                <Download className="size-4" />
                                <span>Current chart</span>
                            </a>
                        </Button>
                    </div>
                </div>

                {flash.success ? (
                    <Alert className="border-green-500/30 bg-green-500/5">
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle className="text-base">
                            1. Choose a file
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-[1fr_220px_auto] md:items-end"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="file">CSV or Excel file</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".csv,.txt,.xlsx"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                {form.errors.file ? (
                                    <p className="text-sm text-destructive">
                                        {form.errors.file}
                                    </p>
                                ) : null}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="mode">
                                    Existing account codes
                                </Label>
                                <Select
                                    value={form.data.mode}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'mode',
                                            value as 'upsert' | 'create',
                                        )
                                    }
                                >
                                    <SelectTrigger id="mode">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="upsert">
                                            Update them
                                        </SelectItem>
                                        <SelectItem value="create">
                                            Leave them unchanged
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="submit"
                                className="gap-2"
                                disabled={!form.data.file || form.processing}
                            >
                                <FileUp className="size-4" />
                                <span>
                                    {form.processing ? 'Checking…' : 'Preview'}
                                </span>
                            </Button>
                        </form>
                        <p className="text-sm text-muted-foreground">
                            Columns:{' '}
                            <code className="text-xs">
                                {columns.join(', ')}
                            </code>
                            . Only <code className="text-xs">account_code</code>{' '}
                            is always required; parents may appear anywhere in
                            the file. Blank cells keep an existing account's
                            value; a new account takes its parent's type and
                            currency.
                        </p>
                    </CardContent>
                </Card>

                {preview ? (
                    <Card className="rounded-lg">
                        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <CardTitle className="text-base">
                                2. Check the changes in {preview.filename}
                            </CardTitle>
                            <div className="flex flex-wrap gap-2 text-sm">
                                <Badge className={actionStyles.create}>
                                    {preview.summary.create} new
                                </Badge>
                                <Badge className={actionStyles.update}>
                                    {preview.summary.update} updated
                                </Badge>
                                <Badge className={actionStyles.unchanged}>
                                    {preview.summary.unchanged} unchanged
                                </Badge>
                                <Badge className={actionStyles.error}>
                                    {preview.summary.error} errors
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {preview.summary.error > 0 ? (
                                <Alert variant="destructive">
                                    <XCircle className="size-4" />
                                    <AlertTitle>
                                        Fix the rows in error and upload the
                                        file again
                                    </AlertTitle>
                                    <AlertDescription>
                                        Nothing is imported while any row has an
                                        error.
                                    </AlertDescription>
                                </Alert>
                            ) : toImport === 0 ? (
                                <Alert>
                                    <CheckCircle2 className="size-4" />
                                    <AlertTitle>Nothing to import</AlertTitle>
                                    <AlertDescription>
                                        Every account in the file is already up
                                        to date.
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Line
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Account
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Result
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Details
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.rows.map((row) => (
                                            <tr
                                                key={row.line}
                                                className="border-t align-top"
                                            >
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {row.line}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span className="font-mono">
                                                        {row.account_code ||
                                                            '—'}
                                                    </span>{' '}
                                                    {row.account_name}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Badge
                                                        className={
                                                            actionStyles[
                                                                row.action
                                                            ]
                                                        }
                                                    >
                                                        {row.action}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {row.errors.length > 0 ? (
                                                        <ul className="space-y-1 text-destructive">
                                                            {row.errors.map(
                                                                (error) => (
                                                                    <li
                                                                        key={
                                                                            error
                                                                        }
                                                                    >
                                                                        {error}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    ) : row.action ===
                                                      'update' ? (
                                                        <ul className="space-y-1">
                                                            {Object.entries(
                                                                row.changes,
                                                            ).map(
                                                                ([
                                                                    field,
                                                                    [from, to],
                                                                ]) => (
                                                                    <li
                                                                        key={
                                                                            field
                                                                        }
                                                                    >
                                                                        <span className="text-muted-foreground">
                                                                            {
                                                                                field
                                                                            }
                                                                            :
                                                                        </span>{' '}
                                                                        <span className="line-through opacity-60">
                                                                            {show(
                                                                                from,
                                                                            )}
                                                                        </span>{' '}
                                                                        →{' '}
                                                                        {show(
                                                                            to,
                                                                        )}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/accounting/chart-of-accounts">
                                        Cancel
                                    </Link>
                                </Button>
                                <Button
                                    type="button"
                                    className="gap-2"
                                    disabled={
                                        !preview.token ||
                                        toImport === 0 ||
                                        importing
                                    }
                                    onClick={importNow}
                                >
                                    <Upload className="size-4" />
                                    <span>
                                        {importing
                                            ? 'Importing…'
                                            : `Import ${toImport} account${toImport === 1 ? '' : 's'}`}
                                    </span>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

ChartOfAccountsImport.layout = {
    breadcrumbs: [
        { title: 'Chart of Accounts', href: '/accounting/chart-of-accounts' },
        { title: 'Import', href: '/accounting/chart-of-accounts/import' },
    ],
};
