import { Head } from '@inertiajs/react';

type AccountNode = {
    id: number;
    account_code: string;
    account_name: string;
    children_recursive?: AccountNode[];
};

function AccountBranch({ account, depth = 0 }: { account: AccountNode; depth?: number }) {
    return (
        <div>
            <div className="border-b py-2 text-sm" style={{ paddingLeft: `${depth * 20}px` }}>
                <span className="font-mono">{account.account_code}</span>
                <span className="ml-3">{account.account_name}</span>
            </div>
            {(account.children_recursive ?? []).map((child) => (
                <AccountBranch key={child.id} account={child} depth={depth + 1} />
            ))}
        </div>
    );
}

export default function ChartOfAccountsTree({ roots }: { roots: AccountNode[] }) {
    return (
        <>
            <Head title="COA Tree" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <h1 className="text-2xl font-semibold">Chart of Accounts Tree</h1>
                <div className="rounded-lg border">
                    {roots.map((root) => <AccountBranch key={root.id} account={root} />)}
                </div>
            </div>
        </>
    );
}

ChartOfAccountsTree.layout = {
    breadcrumbs: [{ title: 'COA Tree', href: '/accounting/chart-of-accounts/tree' }],
};
