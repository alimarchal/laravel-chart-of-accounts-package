import {
    Children,
    Fragment,
    isValidElement,
    useCallback,
    useEffect,
    useId,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {
    ChangeEvent,
    KeyboardEvent as ReactKeyboardEvent,
    ReactElement,
    ReactNode,
} from 'react';
import { createPortal } from 'react-dom';

type OptionProps = {
    value?: string | number;
    disabled?: boolean;
    children?: ReactNode;
    label?: string;
    'data-code'?: string;
    'data-name'?: string;
    'data-tag'?: string;
};

export type DropdownOption = {
    value: string;
    label: string;
    code?: string;
    name?: string;
    tag?: string;
    disabled?: boolean;
    group?: string;
};

type Props = {
    value?: string | number | null;
    defaultValue?: string | number | null;
    /** Same signature as a native <select>: read event.target.value. */
    onChange?: (event: ChangeEvent<HTMLSelectElement>) => void;
    onValueChange?: (value: string) => void;
    children?: ReactNode;
    /** Ready-made options (memoise them): skips reading <option> children, which matters for long lists such as the chart of accounts. */
    options?: DropdownOption[];
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    id?: string;
    name?: string;
    className?: string;
    /** Show the search box: 'auto' (more than 7 options), true or false. */
    search?: 'auto' | boolean;
    /** Offer a clear (x) button when the first option is an empty "none" choice; default on. */
    clearable?: boolean;
    'aria-label'?: string;
    'aria-invalid'?: boolean;
};

const textOf = (node: ReactNode): string => {
    if (node === null || node === undefined || typeof node === 'boolean') {
        return '';
    }

    if (typeof node === 'string' || typeof node === 'number') {
        return String(node);
    }

    if (Array.isArray(node)) {
        return node.map(textOf).join('');
    }

    if (isValidElement(node)) {
        return textOf((node.props as { children?: ReactNode }).children);
    }

    return '';
};

/** Reads the <option> / <optgroup> children the way a native <select> would. */
export function parseOptions(children: ReactNode, group?: string): DropdownOption[] {
    const found: DropdownOption[] = [];

    Children.forEach(children, (child) => {
        if (!isValidElement(child)) {
            return;
        }

        const element = child as ReactElement<
            OptionProps & { children?: ReactNode }
        >;

        if (element.type === Fragment) {
            found.push(...parseOptions(element.props.children, group));
        } else if (element.type === 'optgroup') {
            found.push(
                ...parseOptions(
                    element.props.children,
                    (element.props as { label?: string }).label,
                ),
            );
        } else if (element.type === 'option') {
            const label = textOf(element.props.children);

            found.push({
                value: String(element.props.value ?? label),
                label,
                code: element.props['data-code'],
                name: element.props['data-name'],
                tag: element.props['data-tag'],
                disabled: element.props.disabled,
                group,
            });
        }
    });

    return found;
}

// Only layout classes of the original <select> are kept: the look comes from the component.
const LAYOUT_CLASS =
    /^(w-|min-w-|max-w-|flex$|flex-|grow|shrink|basis-|col-span|row-span|self-|order-|m[trblxy]?-|-m|sm:|md:|lg:|xl:|2xl:)/;

const layoutClasses = (className?: string) =>
    (className ?? '')
        .split(/\s+/)
        .filter((token) => LAYOUT_CLASS.test(token))
        .join(' ');

/**
 * A searchable dropdown that is a drop-in for a native <select>: the same <option> children,
 * value and onChange(event.target.value). Type to filter, arrows + Enter to pick, Esc to close.
 * An option may carry data-code / data-name / data-tag: it is then drawn as code + name + tag
 * (used for the chart of accounts).
 */
export function Dropdown({
    value,
    defaultValue,
    onChange,
    onValueChange,
    children,
    options: given,
    placeholder = 'Select…',
    disabled = false,
    required = false,
    id,
    name,
    className,
    search = 'auto',
    clearable = true,
    'aria-label': ariaLabel,
    'aria-invalid': ariaInvalid,
}: Props) {
    const autoId = useId();
    const listId = `${autoId}-list`;
    const triggerRef = useRef<HTMLButtonElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlighted, setHighlighted] = useState(0);
    const [inner, setInner] = useState<string>(String(defaultValue ?? ''));
    const [box, setBox] = useState<{
        left: number;
        top: number;
        width: number;
        above: boolean;
    } | null>(null);

    const controlled = value !== undefined;
    const current = String(controlled ? (value ?? '') : inner);
    const options = useMemo(() => given ?? parseOptions(children), [given, children]);
    const first = options[0];
    const emptyIsPlaceholder =
        !!first &&
        first.value === '' &&
        !/^(all|any|no |none|head office)/i.test(first.label.trim());
    const emptyIsChoice = !!first && first.value === '' && !emptyIsPlaceholder;
    const choices = options.filter(
        (option) => !(option.value === '' && emptyIsPlaceholder),
    );
    const selected = options.find((option) => option.value === current);
    const showSearch = search === 'auto' ? choices.length > 7 : search;
    const canClear =
        clearable && emptyIsPlaceholder && !required && current !== '';

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return choices;
        }

        return choices.filter((option) =>
            `${option.code ?? ''} ${option.name ?? ''} ${option.label} ${option.tag ?? ''}`
                .toLowerCase()
                .includes(needle),
        );
    }, [choices, query]);

    const commit = useCallback(
        (next: string) => {
            if (!controlled) {
                setInner(next);
            }

            onValueChange?.(next);
            onChange?.({
                target: { value: next, name: name ?? '', id: id ?? '' },
                currentTarget: { value: next, name: name ?? '', id: id ?? '' },
            } as unknown as ChangeEvent<HTMLSelectElement>);
        },
        [controlled, onChange, onValueChange, name, id],
    );

    const place = useCallback(() => {
        const trigger = triggerRef.current;

        if (!trigger) {
            return;
        }

        const rect = trigger.getBoundingClientRect();
        const room = window.innerHeight - rect.bottom;
        const above = room < 300 && rect.top > room;

        setBox({
            left: Math.max(8, Math.min(rect.left, window.innerWidth - 8 - Math.max(rect.width, 288))),
            top: above ? rect.top - 4 : rect.bottom + 4,
            width: Math.max(rect.width, 288),
            above,
        });
    }, []);

    useLayoutEffect(() => {
        if (open) {
            place();
        }
    }, [open, place]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent) => {
            const target = event.target as Node;

            if (
                !triggerRef.current?.contains(target) &&
                !panelRef.current?.contains(target)
            ) {
                setOpen(false);
            }
        };
        const reposition = () => place();

        document.addEventListener('mousedown', close);
        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);

        return () => {
            document.removeEventListener('mousedown', close);
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
        };
    }, [open, place]);

    useEffect(() => {
        if (open) {
            setQuery('');
            const index = choices.findIndex((option) => option.value === current);
            setHighlighted(Math.max(index, 0));
            window.setTimeout(() => searchRef.current?.focus(), 0);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    useEffect(() => {
        listRef.current
            ?.querySelector<HTMLElement>(`[data-index="${highlighted}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [highlighted, open]);

    const pick = (option: DropdownOption) => {
        if (option.disabled) {
            return;
        }

        commit(option.value);
        setOpen(false);
        triggerRef.current?.focus();
    };

    const onKeyDown = (event: ReactKeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlighted((index) => Math.min(index + 1, filtered.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlighted((index) => Math.max(index - 1, 0));
        } else if (event.key === 'Home') {
            setHighlighted(0);
        } else if (event.key === 'End') {
            setHighlighted(filtered.length - 1);
        } else if (event.key === 'Enter') {
            event.preventDefault();

            if (filtered[highlighted]) {
                pick(filtered[highlighted]);
            }
        } else if (event.key === 'Escape') {
            event.stopPropagation();
            setOpen(false);
            triggerRef.current?.focus();
        } else if (event.key === 'Tab') {
            setOpen(false);
        }
    };

    const onTriggerKeyDown = (event: ReactKeyboardEvent<HTMLButtonElement>) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            setOpen(true);
        }
    };

    const label = selected && !(selected.value === '' && emptyIsPlaceholder) ? selected : null;

    return (
        <div className={`relative ${layoutClasses(className)}`}>
            {name ? <input type="hidden" name={name} value={current} /> : null}
            <button
                ref={triggerRef}
                id={id}
                type="button"
                role="combobox"
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={listId}
                aria-label={ariaLabel}
                aria-invalid={ariaInvalid}
                aria-required={required || undefined}
                disabled={disabled}
                onClick={() => setOpen((state) => !state)}
                onKeyDown={onTriggerKeyDown}
                className="flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span
                    className={`min-w-0 flex-1 truncate ${label ? '' : 'text-muted-foreground'}`}
                >
                    {label ? (
                        label.code ? (
                            <>
                                <span className="mr-2 font-mono text-xs font-bold text-primary">
                                    {label.code}
                                </span>
                                {label.name ?? label.label}
                            </>
                        ) : (
                            label.label
                        )
                    ) : (
                        placeholder
                    )}
                </span>
                {canClear ? (
                    <span
                        role="button"
                        tabIndex={-1}
                        aria-label="Clear"
                        className="rounded-sm px-1 text-base leading-none text-muted-foreground hover:text-destructive"
                        onMouseDown={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            commit('');
                        }}
                    >
                        ×
                    </span>
                ) : null}
                <svg
                    aria-hidden
                    viewBox="0 0 20 20"
                    className="size-4 shrink-0 text-muted-foreground"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.6"
                >
                    <path d="m5 8 5 5 5-5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && box
                ? createPortal(
                      <div
                          ref={panelRef}
                          style={{
                              position: 'fixed',
                              left: box.left,
                              width: box.width,
                              zIndex: 9999,
                              ...(box.above
                                  ? { bottom: window.innerHeight - box.top }
                                  : { top: box.top }),
                          }}
                          className="overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-lg"
                          onKeyDown={onKeyDown}
                      >
                          {showSearch ? (
                              <div className="border-b p-2">
                                  <input
                                      ref={searchRef}
                                      type="text"
                                      role="searchbox"
                                      aria-controls={listId}
                                      autoComplete="off"
                                      placeholder="Search…"
                                      value={query}
                                      onChange={(event) => {
                                          setQuery(event.target.value);
                                          setHighlighted(0);
                                      }}
                                      className="h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                  />
                              </div>
                          ) : (
                              <input
                                  ref={searchRef}
                                  readOnly
                                  aria-hidden
                                  tabIndex={-1}
                                  className="sr-only"
                              />
                          )}
                          <ul
                              ref={listRef}
                              id={listId}
                              role="listbox"
                              className="max-h-64 overflow-auto p-1 text-sm"
                          >
                              {emptyIsChoice && first && !query ? (
                                  <li
                                      role="option"
                                      aria-selected={current === ''}
                                      className="cursor-pointer rounded-md px-2 py-1.5 text-muted-foreground hover:bg-accent"
                                      onMouseDown={(event) => {
                                          event.preventDefault();
                                          pick(first);
                                      }}
                                  >
                                      {first.label}
                                  </li>
                              ) : null}
                              {filtered.length === 0 ? (
                                  <li className="px-2 py-3 text-center text-muted-foreground">
                                      No match found
                                  </li>
                              ) : (
                                  filtered.map((option, index) => {
                                      const active = option.value === current;

                                      return (
                                          <li
                                              key={`${option.group ?? ''}:${option.value}`}
                                              data-index={index}
                                              role="option"
                                              aria-selected={active}
                                              aria-disabled={option.disabled}
                                              className={`flex cursor-pointer items-baseline gap-2 rounded-md px-2 py-1.5 ${index === highlighted ? 'bg-accent text-accent-foreground' : ''} ${active ? 'font-semibold' : ''} ${option.disabled ? 'cursor-not-allowed opacity-50' : ''}`}
                                              onMouseEnter={() => setHighlighted(index)}
                                              onMouseDown={(event) => {
                                                  event.preventDefault();
                                                  pick(option);
                                              }}
                                          >
                                              {option.code ? (
                                                  <span className="min-w-14 shrink-0 font-mono text-xs font-bold text-primary">
                                                      {option.code}
                                                  </span>
                                              ) : null}
                                              <span className="min-w-0 flex-1 truncate">
                                                  {option.code ? (option.name ?? option.label) : option.label}
                                              </span>
                                              {option.tag ? (
                                                  <span className="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                      {option.tag}
                                                  </span>
                                              ) : null}
                                              {active ? (
                                                  <span aria-hidden className="shrink-0 text-primary">✓</span>
                                              ) : null}
                                          </li>
                                      );
                                  })
                              )}
                          </ul>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}

export default Dropdown;
