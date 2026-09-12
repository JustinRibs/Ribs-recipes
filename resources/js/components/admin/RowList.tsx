import { ChevronDown, ChevronUp, GripVertical, Trash2 } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

interface RowListProps<T> {
    rows: T[]
    onChange: (rows: T[]) => void
    makeEmpty: () => T
    renderRow: (row: T, index: number, update: (patch: Partial<T>) => void) => ReactNode
    addLabel: string
    emptyLabel: string
    itemNoun: string
}

/**
 * Reorderable rows for ingredients and steps.
 *
 * Move up / move down buttons rather than drag and drop: they work identically
 * with a mouse, a thumb and a keyboard, they announce themselves properly to a
 * screen reader, and they do not fight the page's own scrolling on a phone —
 * which is exactly where a long ingredient list gets reordered.
 */
export function RowList<T>({
    rows,
    onChange,
    makeEmpty,
    renderRow,
    addLabel,
    emptyLabel,
    itemNoun,
}: RowListProps<T>) {
    const move = (from: number, to: number) => {
        if (to < 0 || to >= rows.length) return

        const next = [...rows]
        const [item] = next.splice(from, 1)
        if (item !== undefined) next.splice(to, 0, item)
        onChange(next)
    }

    const update = (index: number, patch: Partial<T>) => {
        onChange(rows.map((row, i) => (i === index ? { ...row, ...patch } : row)))
    }

    const remove = (index: number) => onChange(rows.filter((_, i) => i !== index))

    return (
        <div className="space-y-2">
            {rows.length === 0 && (
                <p className="rounded-xl border border-dashed border-line-strong px-4 py-6 text-center text-[0.92rem] text-ink-muted">
                    {emptyLabel}
                </p>
            )}

            <ul className="space-y-2">
                {rows.map((row, index) => (
                    <li
                        key={index}
                        className="group rounded-xl bg-surface-2/60 p-2.5 ring-1 ring-transparent transition focus-within:bg-surface-2 focus-within:ring-line"
                    >
                        <div className="flex items-start gap-2">
                            <span
                                className="mt-2 hidden size-5 shrink-0 items-center justify-center text-ink-faint sm:flex"
                                aria-hidden="true"
                            >
                                <GripVertical className="size-4" />
                            </span>

                            <div className="min-w-0 flex-1">
                                {renderRow(row, index, (patch) => update(index, patch))}
                            </div>

                            <div className="flex shrink-0 flex-col gap-0.5">
                                <RowButton
                                    label={`Move ${itemNoun} ${index + 1} up`}
                                    onClick={() => move(index, index - 1)}
                                    disabled={index === 0}
                                >
                                    <ChevronUp className="size-4" aria-hidden="true" />
                                </RowButton>
                                <RowButton
                                    label={`Move ${itemNoun} ${index + 1} down`}
                                    onClick={() => move(index, index + 1)}
                                    disabled={index === rows.length - 1}
                                >
                                    <ChevronDown className="size-4" aria-hidden="true" />
                                </RowButton>
                                <RowButton
                                    label={`Remove ${itemNoun} ${index + 1}`}
                                    onClick={() => remove(index)}
                                    destructive
                                >
                                    <Trash2 className="size-4" aria-hidden="true" />
                                </RowButton>
                            </div>
                        </div>
                    </li>
                ))}
            </ul>

            <button
                type="button"
                onClick={() => onChange([...rows, makeEmpty()])}
                className="h-11 w-full rounded-xl border border-dashed border-line-strong text-[0.92rem] font-medium text-ink-muted transition hover:border-adriatic hover:text-adriatic"
            >
                {addLabel}
            </button>
        </div>
    )
}

function RowButton({
    label,
    onClick,
    disabled,
    destructive,
    children,
}: {
    label: string
    onClick: () => void
    disabled?: boolean
    destructive?: boolean
    children: ReactNode
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            className={cn(
                'flex size-10 items-center justify-center rounded-lg transition disabled:opacity-25',
                destructive
                    ? 'text-ink-faint hover:bg-croatia-soft hover:text-croatia'
                    : 'text-ink-faint hover:bg-surface-3 hover:text-ink',
            )}
        >
            {children}
        </button>
    )
}
