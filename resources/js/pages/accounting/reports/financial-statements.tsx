import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Download } from 'lucide-react';
import type { FormEvent } from 'react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccounting } from '@/lib/accounting';

type Type = 'balance-sheet' | 'income-statement' | 'cash-flow';
type Pair = { current: string; compare: string | null };
type AccountRow = {
    account_id: number;
    account_code: string;
    account_name: string;
    amount: Pair;
};
type Line = {
    code: string;
    name: string;
    amount: Pair;
    accounts: AccountRow[];
};
type Section = { key: string; name: string; lines: Line[]; total: Pair };
type FlowLine = {
    label: string;
    amount: string;
    accounts: Array<{
        account_code: string;
        account_name: string;
        amount: string;
    }>;
};

type Props = {
    type: Type;
    statement: Record<string, unknown>;
    filters: Record<string, string>;
};

const tabs: Array<[Type, string]> = [
    ['balance-sheet', 'Balance sheet'],
    ['income-statement', 'Income statement'],
    ['cash-flow', 'Cash flow (indirect)'],
];

const money = (value: string | null | undefined) => {
    if (value === null || value === undefined) {
        return '';
    }
    const amount = Number(value);
    const text = Math.abs(amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return amount < 0 ? `(${text})` : text;
};

export default function FinancialStatements({
    type,
    statement,
    filters,
}: Props) {
    const { permissions } = useAccounting();
    const [values, setValues] = useState(filters);
    const [open, setOpen] = useState<Record<string, boolean>>({});
    const toggle = (key: string) =>
        setOpen((state) => ({ ...state, [key]: !state[key] }));

    const keys =
        type === 'balance-sheet'
            ? ['as_of_date', 'compare_as_of']
            : type === 'income-statement'
              ? ['date_from', 'date_to', 'compare_from', 'compare_to']
              : ['date_from', 'date_to'];
    const params = Object.fromEntries(
        keys.filter((key) => values[key]).map((key) => [key, values[key]]),
    );
    const query = new URLSearchParams(params).toString();

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/accounting/reports/financial-statements',
            { type, ...params },
            { preserveState: true, replace: true },
        );
    };

    const labels: Record<string, string> = {
        as_of_date: 'As of',
        compare_as_of: 'Compare with (as of)',
        date_from: 'From',
        date_to: 'To',
        compare_from: 'Compare from',
        compare_to: 'Compare to',
    };
    const unmapped = Number(statement.unmapped_accounts ?? 0);
    const hasCompare = Boolean(statement.compare_as_of || statement.compare_to);

    return (
        <>
            <Head title="Financial Statements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading
                        title="Financial Statements"
                        description="Laid out by the report lines of Report Mapping. Base currency, posted entries."
                    />
                    <div className="flex flex-wrap gap-2">
                        {(['csv', 'xlsx', 'pdf'] as const).map((format) => (
                            <Button
                                key={format}
                                asChild
                                variant="outline"
                                size="sm"
                                className="gap-1"
                            >
                                <a
                                    href={`/accounting/reports/statement-${type}/export/${format}${query ? `?${query}` : ''}`}
                                >
                                    <Download className="size-3.5" />
                                    {format === 'xlsx'
                                        ? 'Excel'
                                        : format.toUpperCase()}
                                </a>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {tabs.map(([value, label]) => (
                        <Button
                            key={value}
                            asChild
                            size="sm"
                            variant={type === value ? 'default' : 'outline'}
                        >
                            <Link
                                href={`/accounting/reports/financial-statements?type=${value}`}
                            >
                                {label}
                            </Link>
                        </Button>
                    ))}
                </div>

                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-end gap-3"
                >
                    {keys.map((key) => (
                        <div key={key} className="grid gap-1">
                            <Label htmlFor={key}>{labels[key]}</Label>
                            <Input
                                id={key}
                                type="date"
                                className="h-9 w-44"
                                value={values[key] ?? ''}
                                onChange={(event) =>
                                    setValues({
                                        ...values,
                                        [key]: event.target.value,
                                    })
                                }
                            />
                        </div>
                    ))}
                    <Button type="submit">Show</Button>
                </form>

                {unmapped > 0 ? (
                    <Alert>
                        <AlertTitle>
                            {unmapped} accounts are not mapped to a line
                        </AlertTitle>
                        <AlertDescription>
                            They are shown on “unmapped” lines.{' '}
                            {permissions['report-mapping.manage'] ? (
                                <Link
                                    href="/accounting/report-mapping"
                                    className="underline"
                                >
                                    Map them
                                </Link>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {type === 'cash-flow' ? (
                    <CashFlow
                        statement={statement}
                        open={open}
                        toggle={toggle}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">
                                        {type === 'balance-sheet'
                                            ? `As of ${String(statement.as_of)}`
                                            : `${String(statement.from)} to ${String(statement.to)}`}
                                    </th>
                                    <th className="w-40 px-3 py-2 text-right font-medium">
                                        Current
                                    </th>
                                    {hasCompare ? (
                                        <th className="w-40 px-3 py-2 text-right font-medium">
                                            {type === 'balance-sheet'
                                                ? String(
                                                      statement.compare_as_of,
                                                  )
                                                : `${String(statement.compare_from)} – ${String(statement.compare_to)}`}
                                        </th>
                                    ) : null}
                                </tr>
                            </thead>
                            <tbody>
                                {(statement.sections as Section[]).map(
                                    (section) => (
                                        <Fragment key={section.key}>
                                            <tr className="border-t bg-muted/20">
                                                <td
                                                    colSpan={hasCompare ? 3 : 2}
                                                    className="px-3 py-2 font-semibold"
                                                >
                                                    {section.name}
                                                </td>
                                            </tr>
                                            {section.lines.map((line) => {
                                                const key = `${section.key}-${line.code}`;
                                                return (
                                                    <Fragment key={key}>
                                                        <tr className="border-t">
                                                            <td className="px-3 py-1.5 pl-6">
                                                                <button
                                                                    type="button"
                                                                    className="inline-flex items-center gap-1 hover:underline"
                                                                    onClick={() =>
                                                                        toggle(
                                                                            key,
                                                                        )
                                                                    }
                                                                >
                                                                    {line
                                                                        .accounts
                                                                        .length >
                                                                    0 ? (
                                                                        open[
                                                                            key
                                                                        ] ? (
                                                                            <ChevronDown className="size-3.5" />
                                                                        ) : (
                                                                            <ChevronRight className="size-3.5" />
                                                                        )
                                                                    ) : null}
                                                                    {line.name}
                                                                </button>
                                                            </td>
                                                            <td className="px-3 py-1.5 text-right tabular-nums">
                                                                {money(
                                                                    line.amount
                                                                        .current,
                                                                )}
                                                            </td>
                                                            {hasCompare ? (
                                                                <td className="px-3 py-1.5 text-right tabular-nums">
                                                                    {money(
                                                                        line
                                                                            .amount
                                                                            .compare,
                                                                    )}
                                                                </td>
                                                            ) : null}
                                                        </tr>
                                                        {open[key]
                                                            ? line.accounts.map(
                                                                  (account) => (
                                                                      <tr
                                                                          key={`${key}-${account.account_id}`}
                                                                          className="text-muted-foreground"
                                                                      >
                                                                          <td className="px-3 py-1 pl-12">
                                                                              <span className="font-mono">
                                                                                  {
                                                                                      account.account_code
                                                                                  }
                                                                              </span>{' '}
                                                                              {
                                                                                  account.account_name
                                                                              }
                                                                          </td>
                                                                          <td className="px-3 py-1 text-right tabular-nums">
                                                                              {money(
                                                                                  account
                                                                                      .amount
                                                                                      .current,
                                                                              )}
                                                                          </td>
                                                                          {hasCompare ? (
                                                                              <td className="px-3 py-1 text-right tabular-nums">
                                                                                  {money(
                                                                                      account
                                                                                          .amount
                                                                                          .compare,
                                                                                  )}
                                                                              </td>
                                                                          ) : null}
                                                                      </tr>
                                                                  ),
                                                              )
                                                            : null}
                                                    </Fragment>
                                                );
                                            })}
                                            <tr className="border-t font-medium">
                                                <td className="px-3 py-1.5 pl-6">
                                                    Total{' '}
                                                    {section.name.toLowerCase()}
                                                </td>
                                                <td className="px-3 py-1.5 text-right tabular-nums">
                                                    {money(
                                                        section.total.current,
                                                    )}
                                                </td>
                                                {hasCompare ? (
                                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                                        {money(
                                                            section.total
                                                                .compare,
                                                        )}
                                                    </td>
                                                ) : null}
                                            </tr>
                                        </Fragment>
                                    ),
                                )}
                                {Object.entries(
                                    (statement.subtotals ??
                                        statement.totals) as Record<
                                        string,
                                        Pair
                                    >,
                                ).map(([name, value]) => (
                                    <tr
                                        key={name}
                                        className="border-t-2 font-semibold"
                                    >
                                        <td className="px-3 py-2">
                                            {name
                                                .replaceAll('_', ' ')
                                                .replace(/^./, (c) =>
                                                    c.toUpperCase(),
                                                )}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {money(value.current)}
                                        </td>
                                        {hasCompare ? (
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {money(value.compare)}
                                            </td>
                                        ) : null}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

function CashFlow({
    statement,
    open,
    toggle,
}: {
    statement: Record<string, unknown>;
    open: Record<string, boolean>;
    toggle: (key: string) => void;
}) {
    const operating = statement.operating as {
        non_cash: FlowLine[];
        working_capital: FlowLine[];
        unclassified: FlowLine[];
        total: string;
    };
    const investing = statement.investing as {
        lines: FlowLine[];
        total: string;
    };
    const financing = statement.financing as {
        lines: FlowLine[];
        total: string;
    };
    const lines = (group: string, items: FlowLine[]) =>
        items.map((item, index) => {
            const key = `${group}-${index}`;
            return (
                <Fragment key={key}>
                    <tr className="border-t">
                        <td className="px-3 py-1.5 pl-8">
                            <button
                                type="button"
                                className="inline-flex items-center gap-1 hover:underline"
                                onClick={() => toggle(key)}
                            >
                                {open[key] ? (
                                    <ChevronDown className="size-3.5" />
                                ) : (
                                    <ChevronRight className="size-3.5" />
                                )}
                                {item.label}
                            </button>
                        </td>
                        <td className="px-3 py-1.5 text-right tabular-nums">
                            {money(item.amount)}
                        </td>
                    </tr>
                    {open[key]
                        ? item.accounts.map((account) => (
                              <tr
                                  key={`${key}-${account.account_code}`}
                                  className="text-muted-foreground"
                              >
                                  <td className="px-3 py-1 pl-14">
                                      <span className="font-mono">
                                          {account.account_code}
                                      </span>{' '}
                                      {account.account_name}
                                  </td>
                                  <td className="px-3 py-1 text-right tabular-nums">
                                      {money(account.amount)}
                                  </td>
                              </tr>
                          ))
                        : null}
                </Fragment>
            );
        });
    const heading = (text: string) => (
        <tr className="border-t bg-muted/20">
            <td colSpan={2} className="px-3 py-2 font-semibold">
                {text}
            </td>
        </tr>
    );
    const total = (text: string, value: string, strong = false) => (
        <tr
            className={`border-t ${strong ? 'border-t-2 font-semibold' : 'font-medium'}`}
        >
            <td className="px-3 py-1.5 pl-6">{text}</td>
            <td className="px-3 py-1.5 text-right tabular-nums">
                {money(value)}
            </td>
        </tr>
    );

    return (
        <>
            {statement.difference !== '0.00' ? (
                <Alert variant="destructive">
                    <AlertTitle>The cash flow does not reconcile</AlertTitle>
                    <AlertDescription>
                        Difference: {money(String(statement.difference))}
                    </AlertDescription>
                </Alert>
            ) : null}
            <div className="overflow-x-auto rounded-md border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50">
                        <tr>
                            <th className="px-3 py-2 text-left font-medium">
                                {String(statement.from)} to{' '}
                                {String(statement.to)}
                            </th>
                            <th className="w-40 px-3 py-2 text-right font-medium">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {heading('Operating activities')}
                        {total(
                            'Profit for the period',
                            String(statement.profit),
                        )}
                        {operating.non_cash.length > 0
                            ? heading('Adjustments for non-cash items')
                            : null}
                        {lines('non_cash', operating.non_cash)}
                        {operating.working_capital.length > 0
                            ? heading('Changes in working capital')
                            : null}
                        {lines('working', operating.working_capital)}
                        {operating.unclassified.length > 0
                            ? heading('Unclassified (map these accounts)')
                            : null}
                        {lines('unclassified', operating.unclassified)}
                        {total(
                            'Net cash from operating activities',
                            operating.total,
                            true,
                        )}
                        {heading('Investing activities')}
                        {lines('investing', investing.lines)}
                        {total(
                            'Net cash from investing activities',
                            investing.total,
                            true,
                        )}
                        {heading('Financing activities')}
                        {lines('financing', financing.lines)}
                        {total(
                            'Net cash from financing activities',
                            financing.total,
                            true,
                        )}
                        {total(
                            'Net change in cash',
                            String(statement.net_change),
                            true,
                        )}
                        {total(
                            'Cash at the beginning of the period',
                            String(statement.opening_cash),
                        )}
                        {total(
                            'Cash at the end of the period',
                            String(statement.closing_cash),
                            true,
                        )}
                    </tbody>
                </table>
            </div>
            <p className="text-xs text-muted-foreground">
                Cash accounts:{' '}
                {(statement.cash_accounts as string[]).join(', ') || 'none'}
            </p>
        </>
    );
}

FinancialStatements.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        {
            title: 'Financial Statements',
            href: '/accounting/reports/financial-statements',
        },
    ],
};
