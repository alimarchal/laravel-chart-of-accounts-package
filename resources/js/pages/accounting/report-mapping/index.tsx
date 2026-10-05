import { Head, Link, router } from '@inertiajs/react';
import { ListTree, Plus, Sparkles, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Statement = 'balance_sheet' | 'income_statement';

type Line = {
    id: number;
    statement: Statement;
    code: string;
    name: string;
    section: string;
    cash_flow_category: string | null;
    sort_order: number;
    is_system: boolean;
    accounts_count: number;
};

type Account = {
    id: number;
    account_code: string;
    account_name: string;
    parent_id: number | null;
    is_group: boolean;
    is_active: boolean;
    statement: Statement;
    report_line_id: number | null;
    cash_flow_category: string | null;
    resolved_line_id: number | null;
    resolved_cash_flow_category: string | null;
    inherited: boolean;
};

type Props = {
    lines: Line[];
    accounts: Account[];
    sections: Record<Statement, Record<string, string>>;
    cashFlowCategories: Record<string, string>;
    unmapped: number;
};

const selectClass =
    'h-8 w-full rounded-md border border-input bg-background px-2 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function ReportMapping({
    lines,
    accounts,
    sections,
    cashFlowCategories,
    unmapped,
}: Props) {
    const { flash } = useAccounting();
    const [statement, setStatement] = useState<Statement>('balance_sheet');
    const [onlyUnmapped, setOnlyUnmapped] = useState(false);
    const [search, setSearch] = useState('');
    const [newLine, setNewLine] = useState({
        code: '',
        name: '',
        section: '',
        cash_flow_category: '',
    });

    const statementLines = lines.filter((line) => line.statement === statement);
    const lineById = useMemo(
        () => new Map(lines.map((line) => [line.id, line])),
        [lines],
    );
    const depth = useMemo(() => {
        const byId = new Map(accounts.map((account) => [account.id, account]));
        const result = new Map<number, number>();
        for (const account of accounts) {
            let level = 0;
            let parent = account.parent_id;
            while (parent !== null && level < 20) {
                level++;
                parent = byId.get(parent)?.parent_id ?? null;
            }
            result.set(account.id, level);
        }
        return result;
    }, [accounts]);

    const visible = accounts.filter(
        (account) =>
            account.statement === statement &&
            (!onlyUnmapped ||
                (account.resolved_line_id === null && !account.is_group)) &&
            (search === '' ||
                `${account.account_code} ${account.account_name}`
                    .toLowerCase()
                    .includes(search.toLowerCase())),
    );

    const save = (account: Account, lineId: string, category: string) =>
        router.put(
            `/accounting/chart-of-accounts/${account.id}/report-mapping`,
            {
                report_line_id: lineId === '' ? null : Number(lineId),
                cash_flow_category: category === '' ? null : category,
            },
            { preserveScroll: true, preserveState: true },
        );

    const addLine = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(
            '/accounting/report-lines',
            {
                ...newLine,
                statement,
                cash_flow_category:
                    newLine.cash_flow_category === ''
                        ? null
                        : newLine.cash_flow_category,
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setNewLine({
                        code: '',
                        name: '',
                        section: '',
                        cash_flow_category: '',
                    }),
            },
        );
    };

    const renameLine = (line: Line) => {
        const name = window.prompt('Line name', line.name);
        if (name && name !== line.name) {
            router.put(
                `/accounting/report-lines/${line.id}`,
                { name },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Report Mapping" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Report Mapping"
                        description="Which line of the financial statements each account reports under. Map a group and every account under it follows."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            className="gap-2"
                            onClick={() =>
                                router.post(
                                    '/accounting/report-mapping/recommended',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Sparkles className="size-4" />
                            Apply recommended mapping
                        </Button>
                        <Button asChild variant="outline" className="gap-2">
                            <Link href="/accounting/reports/financial-statements">
                                <ListTree className="size-4" />
                                Financial statements
                            </Link>
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
                {unmapped > 0 ? (
                    <Alert>
                        <AlertTitle>
                            {unmapped} posting accounts are not mapped
                        </AlertTitle>
                        <AlertDescription>
                            They appear on an “unmapped” line of their section,
                            and their movements as unclassified in the cash
                            flow.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="flex flex-wrap items-center gap-2">
                    {(['balance_sheet', 'income_statement'] as Statement[]).map(
                        (value) => (
                            <Button
                                key={value}
                                variant={
                                    statement === value ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() => setStatement(value)}
                            >
                                {value === 'balance_sheet'
                                    ? 'Balance sheet'
                                    : 'Income statement'}
                            </Button>
                        ),
                    )}
                    <label className="ml-2 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={onlyUnmapped}
                            onChange={(event) =>
                                setOnlyUnmapped(event.target.checked)
                            }
                        />
                        Unmapped only
                    </label>
                    <Input
                        className="ml-auto h-8 w-60"
                        placeholder="Search accounts"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-[1fr_380px]">
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Account
                                    </th>
                                    <th className="w-64 px-3 py-2 font-medium">
                                        Line
                                    </th>
                                    {statement === 'balance_sheet' ? (
                                        <th className="w-56 px-3 py-2 font-medium">
                                            Cash flow
                                        </th>
                                    ) : null}
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((account) => (
                                    <tr key={account.id} className="border-t">
                                        <td
                                            className="px-3 py-1.5"
                                            style={{
                                                paddingLeft: `${0.75 + (depth.get(account.id) ?? 0) * 1}rem`,
                                            }}
                                        >
                                            <span className="font-mono">
                                                {account.account_code}
                                            </span>{' '}
                                            <span
                                                className={
                                                    account.is_group
                                                        ? 'font-semibold'
                                                        : ''
                                                }
                                            >
                                                {account.account_name}
                                            </span>
                                            {!account.is_active ? (
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    (inactive)
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-1.5">
                                            <select
                                                className={selectClass}
                                                value={
                                                    account.report_line_id ?? ''
                                                }
                                                onChange={(event) =>
                                                    save(
                                                        account,
                                                        event.target.value,
                                                        account.cash_flow_category ??
                                                            '',
                                                    )
                                                }
                                                aria-label={`Line of ${account.account_code}`}
                                            >
                                                <option value="">
                                                    {account.inherited &&
                                                    account.resolved_line_id
                                                        ? `↳ ${lineById.get(account.resolved_line_id)?.name ?? ''}`
                                                        : '— not mapped —'}
                                                </option>
                                                {statementLines.map((line) => (
                                                    <option
                                                        key={line.id}
                                                        value={line.id}
                                                    >
                                                        {line.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        {statement === 'balance_sheet' ? (
                                            <td className="px-3 py-1.5">
                                                <select
                                                    className={selectClass}
                                                    value={
                                                        account.cash_flow_category ??
                                                        ''
                                                    }
                                                    onChange={(event) =>
                                                        save(
                                                            account,
                                                            account.report_line_id ===
                                                                null
                                                                ? ''
                                                                : String(
                                                                      account.report_line_id,
                                                                  ),
                                                            event.target.value,
                                                        )
                                                    }
                                                    aria-label={`Cash flow of ${account.account_code}`}
                                                >
                                                    <option value="">
                                                        {account.resolved_cash_flow_category
                                                            ? `↳ ${cashFlowCategories[account.resolved_cash_flow_category]}`
                                                            : '— unclassified —'}
                                                    </option>
                                                    {Object.entries(
                                                        cashFlowCategories,
                                                    ).map(([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                        ) : null}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <Card className="h-fit rounded-lg">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Lines of the{' '}
                                {statement === 'balance_sheet'
                                    ? 'balance sheet'
                                    : 'income statement'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {Object.entries(sections[statement]).map(
                                ([section, label]) => (
                                    <div key={section}>
                                        <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            {label}
                                        </div>
                                        <ul className="mt-1 space-y-1">
                                            {statementLines
                                                .filter(
                                                    (line) =>
                                                        line.section ===
                                                        section,
                                                )
                                                .map((line) => (
                                                    <li
                                                        key={line.id}
                                                        className="flex items-center justify-between gap-2 text-sm"
                                                    >
                                                        <button
                                                            type="button"
                                                            className="text-left hover:underline"
                                                            onClick={() =>
                                                                renameLine(line)
                                                            }
                                                            title="Rename"
                                                        >
                                                            {line.name}
                                                        </button>
                                                        <span className="flex items-center gap-1">
                                                            {line.cash_flow_category ? (
                                                                <Badge variant="outline">
                                                                    {
                                                                        line.cash_flow_category
                                                                    }
                                                                </Badge>
                                                            ) : null}
                                                            <Badge variant="secondary">
                                                                {
                                                                    line.accounts_count
                                                                }
                                                            </Badge>
                                                            {!line.is_system ? (
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="size-6"
                                                                    title="Delete line"
                                                                    onClick={() => {
                                                                        if (
                                                                            window.confirm(
                                                                                `Delete the line ${line.name}?`,
                                                                            )
                                                                        ) {
                                                                            router.delete(
                                                                                `/accounting/report-lines/${line.id}`,
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            );
                                                                        }
                                                                    }}
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </Button>
                                                            ) : null}
                                                        </span>
                                                    </li>
                                                ))}
                                        </ul>
                                    </div>
                                ),
                            )}

                            <form
                                onSubmit={addLine}
                                className="space-y-2 border-t pt-3"
                            >
                                <div className="text-sm font-medium">
                                    Add a line
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="line_code">Code</Label>
                                        <Input
                                            id="line_code"
                                            value={newLine.code}
                                            onChange={(event) =>
                                                setNewLine({
                                                    ...newLine,
                                                    code: event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="line_section">
                                            Section
                                        </Label>
                                        <select
                                            id="line_section"
                                            className={selectClass}
                                            value={newLine.section}
                                            onChange={(event) =>
                                                setNewLine({
                                                    ...newLine,
                                                    section: event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">Choose</option>
                                            {Object.entries(
                                                sections[statement],
                                            ).map(([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="line_name">Name</Label>
                                    <Input
                                        id="line_name"
                                        value={newLine.name}
                                        onChange={(event) =>
                                            setNewLine({
                                                ...newLine,
                                                name: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                {statement === 'balance_sheet' ? (
                                    <div className="grid gap-1">
                                        <Label htmlFor="line_cash_flow">
                                            Cash flow
                                        </Label>
                                        <select
                                            id="line_cash_flow"
                                            className={selectClass}
                                            value={newLine.cash_flow_category}
                                            onChange={(event) =>
                                                setNewLine({
                                                    ...newLine,
                                                    cash_flow_category:
                                                        event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                Unclassified
                                            </option>
                                            {Object.entries(
                                                cashFlowCategories,
                                            ).map(([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ) : null}
                                <Button
                                    type="submit"
                                    size="sm"
                                    className="gap-2"
                                    disabled={
                                        !newLine.code ||
                                        !newLine.name ||
                                        !newLine.section
                                    }
                                >
                                    <Plus className="size-4" />
                                    Add line
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ReportMapping.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Report Mapping', href: '/accounting/report-mapping' },
    ],
};
