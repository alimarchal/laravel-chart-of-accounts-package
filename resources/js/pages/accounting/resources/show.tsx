import { Head, Link, usePage } from '@inertiajs/react';
import { Edit } from 'lucide-react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { playErrorSound, playSuccessSound } from '@/lib/sounds';

type Field = {
    name: string;
    label: string;
};

type RecordValue = string | number | boolean | null;

type Props = {
    title: string;
    routeName: string;
    fields: Field[];
    record: {
        id: number;
        [key: string]: RecordValue;
    };
};

function displayValue(value: RecordValue): string {
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return value === null ? '' : String(value);
}

export default function AccountingResourceShow({ title, routeName, fields, record }: Props) {
    const { auth, flash } = usePage().props;
    const permissions = auth.accountingPermissions ?? {};

    useEffect(() => {
        if (flash.success) {
            playSuccessSound();
        }
    }, [flash.success]);

    useEffect(() => {
        if (flash.error) {
            playErrorSound();
        }
    }, [flash.error]);

    return (
        <>
            <Head title={`${title} #${record.id}`} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <Heading title={`${title} #${record.id}`} description="Review this accounting setup record." />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/accounting/${routeName}`}>Back</Link>
                        </Button>
                        {permissions[`${routeName}.update`] === true ? (
                            <Button asChild>
                            <Link href={`/accounting/${routeName}/${record.id}/edit`}>
                                <Edit className="size-4" />
                                Edit
                            </Link>
                        </Button>
                        ) : null}
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

                <div className="grid max-w-4xl grid-cols-1 overflow-hidden rounded-lg border md:grid-cols-2">
                    <div className="border-b bg-muted/40 p-3 font-medium md:col-span-2">Details</div>
                    <div className="border-b p-3 text-sm text-muted-foreground">ID</div>
                    <div className="border-b p-3 text-sm">{record.id}</div>
                    {fields.map((field) => (
                        <div key={field.name} className="contents">
                            <div className="border-b p-3 text-sm text-muted-foreground">{field.label}</div>
                            <div className="border-b p-3 text-sm">{displayValue(record[field.name])}</div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

AccountingResourceShow.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
