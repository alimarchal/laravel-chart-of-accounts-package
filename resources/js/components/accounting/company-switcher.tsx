import { router } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useAccounting } from '@/lib/accounting';

/**
 * Shows the company the accounting pages work in and, with multi-company enabled and more than one
 * accessible company, lets the user switch. Drop it into your sidebar or header:
 * <CompanySwitcher /> (it renders nothing for single-company installs).
 */
export function CompanySwitcher({ className = '' }: { className?: string }) {
    const { company } = useAccounting();

    if (!company.enabled || !company.current) {
        return null;
    }

    if (company.list.length < 2) {
        return (
            <div className={`flex items-center gap-2 text-sm ${className}`}>
                <Building2 className="size-4 text-muted-foreground" />
                <span className="font-medium">{company.current.name}</span>
            </div>
        );
    }

    return (
        <label className={`flex items-center gap-2 text-sm ${className}`}>
            <Building2 className="size-4 text-muted-foreground" />
            <span className="sr-only">Company</span>
            <select
                aria-label="Company"
                className="h-9 rounded-md border bg-transparent px-2 text-sm font-medium"
                value={company.current.id}
                onChange={(event) =>
                    router.post('/accounting/company/switch', {
                        company_id: Number(event.target.value),
                    })
                }
            >
                {company.list.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name} ({item.code})
                    </option>
                ))}
            </select>
        </label>
    );
}

export default CompanySwitcher;
