import { useEffect, useId, useMemo, useRef, useState } from 'react';

export type SearchableSelectOption = { value: string; label: string };

type Props = {
    value: string | null | undefined;
    options: SearchableSelectOption[];
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
};

/**
 * Dependency-free searchable select (combobox): type to filter, arrow keys + Enter to pick,
 * Escape to close. Styled with Tailwind to match the starter kits' inputs.
 */
export function SearchableSelect({
    value,
    options,
    onChange,
    placeholder = 'Search…',
    disabled = false,
}: Props) {
    const listId = useId();
    const containerRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlighted, setHighlighted] = useState(0);

    const selected = options.find(
        (option) => option.value === String(value ?? ''),
    );

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return needle === ''
            ? options
            : options.filter((option) =>
                  option.label.toLowerCase().includes(needle),
              );
    }, [options, query]);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const choose = (option: SearchableSelectOption) => {
        onChange(option.value);
        setQuery('');
        setOpen(false);
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setHighlighted((index) => Math.min(index + 1, filtered.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlighted((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Enter' && open && filtered[highlighted]) {
            event.preventDefault();
            choose(filtered[highlighted]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div ref={containerRef} className="relative">
            <input
                type="text"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                disabled={disabled}
                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder={selected?.label ?? placeholder}
                value={open ? query : (selected?.label ?? '')}
                onFocus={() => {
                    setOpen(true);
                    setHighlighted(0);
                }}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setHighlighted(0);
                    setOpen(true);
                }}
                onKeyDown={onKeyDown}
            />
            {open && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
                >
                    {filtered.length === 0 ? (
                        <li className="px-2 py-1.5 text-muted-foreground">
                            No results
                        </li>
                    ) : (
                        filtered.map((option, index) => (
                            <li
                                key={option.value}
                                role="option"
                                aria-selected={
                                    option.value === String(value ?? '')
                                }
                                className={`cursor-pointer rounded-sm px-2 py-1.5 ${index === highlighted ? 'bg-accent text-accent-foreground' : ''}`}
                                onMouseEnter={() => setHighlighted(index)}
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    choose(option);
                                }}
                            >
                                {option.label}
                            </li>
                        ))
                    )}
                </ul>
            )}
        </div>
    );
}

export default SearchableSelect;
