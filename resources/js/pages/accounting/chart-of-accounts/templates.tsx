import { Head, Link, router } from '@inertiajs/react';
import { Check, LayoutTemplate } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAccounting } from '@/lib/accounting';

type Template = {
    key: string;
    name: string;
    description: string;
    base: string;
    accounts: number;
    extras: number;
};
type Row = {
    account_code: string;
    account_name: string;
    parent_code: string | null;
    type: string;
    is_group: boolean;
    extra: boolean;
    status: 'new' | 'exists' | 'different' | 'blocked';
    existing_name: string | null;
    line: string | null;
};
type Preview = {
    template: { key: string; name: string; description: string };
    rows: Row[];
    summary: {
        new: number;
        exists: number;
        different: number;
        blocked: number;
    };
};

type Props = {
    templates: Template[];
    preview: Preview | null;
    selected: string;
};

const statusStyle: Record<Row['status'], string> = {
    new: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    exists: 'bg-muted text-muted-foreground',
    different:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    blocked: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

export default function ChartTemplates({
    templates,
    preview,
    selected,
}: Props) {
    const { flash } = useAccounting();
    const [onlyNew, setOnlyNew] = useState(true);
    const [busy, setBusy] = useState(false);
    const rows = preview
        ? preview.rows.filter((row) => !onlyNew || row.status !== 'exists')
        : [];

    return (
        <>
            <Head title="Chart Templates" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading
                    title="Chart Templates"
                    description="Start from an industry chart, or add an industry's accounts to the chart you have. Accounts that exist are never changed."
                />

                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {templates.map((template) => (
                        <Card
                            key={template.key}
                            className={`rounded-lg ${selected === template.key ? 'border-primary' : ''}`}
                        >
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <LayoutTemplate className="size-4" />
                                    {template.name}
                                </CardTitle>
                                <CardDescription>
                                    {template.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {template.accounts} accounts
                                    {template.extras > 0
                                        ? `, ${template.extras} industry-specific`
                                        : ''}
                                </span>
                                <Button
                                    asChild
                                    size="sm"
                                    variant={
                                        selected === template.key
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    <Link
                                        href={`/accounting/chart-templates?template=${template.key}`}
                                        preserveScroll
                                    >
                                        Preview
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {preview ? (
                    <Card className="rounded-lg">
                        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <CardTitle className="text-base">
                                {preview.template.name}: what would be added
                            </CardTitle>
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Badge className={statusStyle.new}>
                                    {preview.summary.new} new
                                </Badge>
                                <Badge className={statusStyle.exists}>
                                    {preview.summary.exists} already there
                                </Badge>
                                {preview.summary.different > 0 ? (
                                    <Badge className={statusStyle.different}>
                                        {preview.summary.different} named
                                        differently
                                    </Badge>
                                ) : null}
                                {preview.summary.blocked > 0 ? (
                                    <Badge className={statusStyle.blocked}>
                                        {preview.summary.blocked} blocked
                                    </Badge>
                                ) : null}
                                <label className="ml-2 flex items-center gap-1">
                                    <input
                                        type="checkbox"
                                        checked={onlyNew}
                                        onChange={(event) =>
                                            setOnlyNew(event.target.checked)
                                        }
                                    />
                                    Hide accounts already there
                                </label>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="max-h-[28rem] overflow-y-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 bg-muted/60 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Code
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Account
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Parent
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Type
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Statement line
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr
                                                key={row.account_code}
                                                className="border-t"
                                            >
                                                <td className="px-3 py-1.5 font-mono">
                                                    {row.account_code}
                                                </td>
                                                <td
                                                    className={`px-3 py-1.5 ${row.is_group ? 'font-semibold' : ''}`}
                                                >
                                                    {row.account_name}
                                                    {row.existing_name ? (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            (yours:{' '}
                                                            {row.existing_name})
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-1.5 font-mono text-muted-foreground">
                                                    {row.parent_code ?? '—'}
                                                </td>
                                                <td className="px-3 py-1.5">
                                                    {row.type}
                                                </td>
                                                <td className="px-3 py-1.5 text-muted-foreground">
                                                    {row.line ?? '—'}
                                                </td>
                                                <td className="px-3 py-1.5">
                                                    <Badge
                                                        className={
                                                            statusStyle[
                                                                row.status
                                                            ]
                                                        }
                                                    >
                                                        {row.status}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="flex justify-end">
                                <Button
                                    className="gap-2"
                                    disabled={busy || preview.summary.new === 0}
                                    onClick={() =>
                                        router.post(
                                            `/accounting/chart-templates/${preview.template.key}/apply`,
                                            {},
                                            {
                                                onStart: () => setBusy(true),
                                                onFinish: () => setBusy(false),
                                            },
                                        )
                                    }
                                >
                                    <Check className="size-4" />
                                    {preview.summary.new === 0
                                        ? 'Nothing to add'
                                        : `Add ${preview.summary.new} accounts`}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

ChartTemplates.layout = {
    breadcrumbs: [
        { title: 'Chart of Accounts', href: '/accounting/chart-of-accounts' },
        { title: 'Templates', href: '/accounting/chart-templates' },
    ],
};
