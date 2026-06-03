import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    rows: Array<Record<string, string | number | null>>;
    exportBase: string;
};

export default function AccountingReportTable({ title, rows, exportBase }: Props) {
    const columns = Object.keys(rows[0] ?? {});

    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <div className="flex flex-wrap gap-2">
                        {['csv', 'xlsx', 'pdf'].map((format) => (
                            <Button key={format} asChild variant="outline">
                                <Link href={`${exportBase}/${format}`}>
                                    <Download className="size-4" />
                                    {format.toUpperCase()}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {columns.map((column) => (
                                    <th key={column} className="p-3 font-medium capitalize">
                                        {column.replaceAll('_', ' ')}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={index} className="border-t">
                                    {columns.map((column) => (
                                        <td key={column} className="p-3">
                                            {row[column] ?? '-'}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AccountingReportTable.layout = {
    breadcrumbs: [{ title: 'Accounting Reports', href: '/accounting' }],
};
