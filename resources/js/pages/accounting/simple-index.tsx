import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Props = {
    records: {
        data: Array<Record<string, unknown>>;
    };
};

export default function SimpleAccountingIndex({ records }: Props) {
    const first = records.data[0] ?? {};
    const columns = Object.keys(first).filter((key) => !['created_at', 'updated_at', 'deleted_at'].includes(key));

    return (
        <>
            <Head title="Accounting Records" />
            <div className="space-y-6 p-4">
                <Heading title="Accounting Records" description="Review accounting data in a compact operational table." />
                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>{columns.map((column) => <th key={column} className="p-3 font-medium">{column}</th>)}</tr>
                        </thead>
                        <tbody>
                            {records.data.map((record, index) => (
                                <tr key={String(record.id ?? index)} className="border-t">
                                    {columns.map((column) => <td key={column} className="p-3">{String(record[column] ?? '')}</td>)}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

SimpleAccountingIndex.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
