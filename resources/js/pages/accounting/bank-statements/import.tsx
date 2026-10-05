import { Head, Link, router, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Line = {
    line: number;
    txn_date: string | null;
    description: string | null;
    reference: string | null;
    deposit: string;
    withdrawal: string;
    balance: string | null;
    status: 'new' | 'duplicate' | 'error';
    error: string | null;
};
type Preview = {
    lines: Line[];
    summary: { new: number; duplicate: number; error: number };
    from_date: string | null;
    to_date: string | null;
    closing_balance: string | null;
    token: string | null;
    filename: string;
    bank_account_id: number;
};
type Props = {
    banks: Array<{ id: number; name: string }>;
    preview: Preview | null;
    matchDays: number;
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const style: Record<Line['status'], string> = {
    new: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    duplicate: 'bg-muted text-muted-foreground',
    error: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};
const money = (value: string) =>
    Number(value) === 0
        ? ''
        : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 });

export default function BankStatementImport({ banks, preview }: Props) {
    const { flash } = useAccounting();
    const upload = useForm<{ bank_account_id: string; file: File | null }>({
        bank_account_id: String(banks[0]?.id ?? ''),
        file: null,
    });
    const [closing, setClosing] = useState(preview?.closing_balance ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        upload.post('/accounting/bank-statements/import/preview', {
            forceFormData: true,
        });
    };
    const confirm = () =>
        router.post('/accounting/bank-statements/import', {
            token: preview?.token,
            closing_balance: closing === '' ? null : closing,
        });

    return (
        <>
            <Head title="Import Bank Statement" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <Heading
                    title="Import Bank Statement"
                    description="Upload a CSV or Excel statement. Columns are recognised by name (Date, Description, Reference, Withdrawal / Debit, Deposit / Credit or a signed Amount, Balance). Transactions imported before are skipped."
                />

                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Statement file</CardTitle>
                        <CardDescription>
                            Nothing is saved until you confirm the preview.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_id">Bank account</Label>
                                <select
                                    id="bank_account_id"
                                    className={selectClass}
                                    value={upload.data.bank_account_id}
                                    onChange={(event) => upload.setData('bank_account_id', event.target.value)}
                                >
                                    {banks.map((bank) => (
                                        <option key={bank.id} value={bank.id}>
                                            {bank.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={upload.errors.bank_account_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="file">File</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".csv,.txt,.xlsx"
                                    onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)}
                                />
                                <InputError message={upload.errors.file} />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button type="submit" className="gap-2" disabled={upload.processing || !upload.data.file}>
                                    <Upload className="size-4" />
                                    Preview
                                </Button>
                                <Button asChild variant="ghost">
                                    <Link href="/accounting/bank-statements">Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {preview ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>{preview.filename}</CardTitle>
                            <CardDescription>
                                {preview.summary.new} new · {preview.summary.duplicate} already imported ·{' '}
                                {preview.summary.error} with errors
                                {preview.from_date ? ` · ${preview.from_date} → ${preview.to_date}` : ''}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="max-h-[28rem] overflow-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 bg-muted text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Row</th>
                                            <th className="px-3 py-2 font-medium">Date</th>
                                            <th className="px-3 py-2 font-medium">Description</th>
                                            <th className="px-3 py-2 font-medium">Reference</th>
                                            <th className="px-3 py-2 text-right font-medium">Withdrawal</th>
                                            <th className="px-3 py-2 text-right font-medium">Deposit</th>
                                            <th className="px-3 py-2 font-medium">Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.lines.map((line) => (
                                            <tr key={line.line} className="border-t">
                                                <td className="px-3 py-2">{line.line}</td>
                                                <td className="px-3 py-2">{line.txn_date ?? '—'}</td>
                                                <td className="px-3 py-2">{line.description}</td>
                                                <td className="px-3 py-2">{line.reference}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{money(line.withdrawal)}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{money(line.deposit)}</td>
                                                <td className="px-3 py-2">
                                                    <Badge className={style[line.status]}>{line.status}</Badge>
                                                    {line.error ? (
                                                        <span className="ml-2 text-xs text-red-700">{line.error}</span>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {preview.token ? (
                                <div className="flex flex-wrap items-end gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="closing_balance">Statement closing balance</Label>
                                        <Input
                                            id="closing_balance"
                                            type="number"
                                            step="0.01"
                                            value={closing}
                                            onChange={(event) => setClosing(event.target.value)}
                                        />
                                    </div>
                                    <Button onClick={confirm} disabled={preview.summary.new === 0}>
                                        Import {preview.summary.new} transactions
                                    </Button>
                                </div>
                            ) : (
                                <Alert variant="destructive">
                                    <AlertTitle>Fix the file</AlertTitle>
                                    <AlertDescription>
                                        Some rows have errors: correct them and upload the file again.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

BankStatementImport.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Bank Statements', href: '/accounting/bank-statements' },
        { title: 'Import', href: '/accounting/bank-statements/import' },
    ],
};
