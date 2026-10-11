import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type FilterField = {
    name: string;
    label: string;
    type?: 'text' | 'select' | 'date' | 'number';
    options?: Record<string, string>;
    placeholder?: string;
    /** A setting such as "as of" that is always present and does not count as an active filter. */
    quiet?: boolean;
};

type Props = {
    path: string;
    fields: FilterField[];
    values: Record<string, string | number | null | undefined>;
    /** Parameters of other filter bars on the same page that this one must not drop. */
    preserve?: Record<string, string | number | null | undefined>;
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

/**
 * The filter bar of the list screens. The fields use the same names as the API (`?name=…` or `?filter[name]=…`),
 * so a link to a filtered screen and a call to the API look alike.
 */
export default function FilterBar({ path, fields, values, preserve = {} }: Props) {
    const kept = Object.fromEntries(Object.entries(preserve).filter(([, value]) => value !== null && value !== undefined && String(value) !== ''));
    const [state, setState] = useState<Record<string, string>>(() =>
        Object.fromEntries(fields.map((field) => [field.name, String(values[field.name] ?? '')])),
    );
    const active = fields.filter((field) => !field.quiet && String(values[field.name] ?? '') !== '').length;
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get(path, { ...kept, ...Object.fromEntries(Object.entries(state).filter(([, value]) => value !== '')) }, { preserveScroll: true });
    };
    const reset = () => {
        setState(Object.fromEntries(fields.map((field) => [field.name, ''])));
        router.get(path, kept, { preserveScroll: true });
    };

    return (
        <form onSubmit={apply} className="flex flex-wrap items-end gap-3" role="search">
            {fields.map((field) => (
                <label key={field.name} className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                    <span>{field.label}</span>
                    {field.type === 'select' ? (
                        <select
                            className={selectClass}
                            value={state[field.name]}
                            onChange={(event) => setState({ ...state, [field.name]: event.target.value })}
                            aria-label={field.label}
                        >
                            <option value="">All</option>
                            {Object.entries(field.options ?? {}).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    ) : (
                        <Input
                            className={field.type === 'date' || field.type === 'number' ? 'w-36' : 'w-56'}
                            type={field.type === 'date' ? 'date' : field.type === 'number' ? 'number' : 'search'}
                            step={field.type === 'number' ? 'any' : undefined}
                            placeholder={field.placeholder}
                            value={state[field.name]}
                            onChange={(event) => setState({ ...state, [field.name]: event.target.value })}
                            aria-label={field.label}
                        />
                    )}
                </label>
            ))}
            <Button type="submit" variant="outline">
                Filter
            </Button>
            {active > 0 ? (
                <Button type="button" variant="ghost" onClick={reset}>
                    Reset ({active})
                </Button>
            ) : null}
        </form>
    );
}

/** The active filters as a query string ("?as_of_date=…"), for links that must carry them (exports, prints). */
export const filterQuery = (values: Record<string, string | number | null | undefined>): string => {
    const query = new URLSearchParams(
        Object.entries(values)
            .filter(([, value]) => value !== null && value !== undefined && String(value) !== '')
            .map(([key, value]) => [key, String(value)]),
    ).toString();

    return query === '' ? '' : `?${query}`;
};
