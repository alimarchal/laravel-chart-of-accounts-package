import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { linkEnabled, useAccounting } from '@/lib/accounting';

/**
 * An outline button that links to another accounting screen, left out when that screen's feature is switched off.
 */
export function FeatureLink({ href, children }: { href: string; children: ReactNode }) {
    const { disabledPaths } = useAccounting();

    if (!linkEnabled(href, disabledPaths)) {
        return null;
    }

    return (
        <Button asChild variant="outline">
            <Link href={href}>{children}</Link>
        </Button>
    );
}
