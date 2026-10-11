import { router } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';
import { useAccounting } from '@/lib/accounting';

/**
 * The branch the accounting pages work in. With branches enabled it lets the user pick one branch (new entries are booked to it and lists
 * and reports follow it) or "All branches". Drop it into your sidebar or header: <BranchSwitcher /> (it renders nothing without branches).
 */
export function BranchSwitcher({ className = '' }: { className?: string }) {
    const { branch } = useAccounting();

    if (!branch.enabled || branch.list.length === 0) {
        return null;
    }

    return (
        <label className={`flex items-center gap-2 text-sm ${className}`}>
            <GitBranch className="size-4 text-muted-foreground" />
            <span className="sr-only">Branch</span>
            <select
                aria-label="Branch"
                className="h-9 rounded-md border bg-transparent px-2 text-sm font-medium"
                value={branch.current?.id ?? ''}
                onChange={(event) =>
                    router.post(
                        '/accounting/branch/switch',
                        { branch_id: event.target.value === '' ? null : Number(event.target.value) },
                        { preserveScroll: true },
                    )
                }
            >
                <option value="">{branch.restricted ? 'All my branches' : 'All branches'}</option>
                {branch.list.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name} ({item.code})
                    </option>
                ))}
            </select>
        </label>
    );
}

export default BranchSwitcher;
