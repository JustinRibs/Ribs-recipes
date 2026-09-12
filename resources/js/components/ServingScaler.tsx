import { Minus, Plus } from 'lucide-react'
import { cn } from '@/lib/cn'

interface ServingScalerProps {
    baseServings: number
    servings: number
    label: string
    onChange: (servings: number) => void
    className?: string
    compact?: boolean
}

const PRESETS = [0.5, 1, 2, 3]

/**
 * Serving control.
 *
 * Two ways to drive it, because people think about this differently: nudge the
 * serving count directly, or pick a multiplier. Both write the same value, and
 * the ingredient list re-renders from it.
 */
export function ServingScaler({
    baseServings,
    servings,
    label,
    onChange,
    className,
    compact = false,
}: ServingScalerProps) {
    const factor = baseServings > 0 ? servings / baseServings : 1

    const clamp = (value: number) => Math.min(99, Math.max(1, Math.round(value)))

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex items-center justify-between gap-4">
                <span className="text-sm font-medium text-ink-muted">
                    {compact ? 'Servings' : `How many ${label}?`}
                </span>

                <div className="flex items-center gap-1 rounded-full bg-surface-2 p-1">
                    <button
                        type="button"
                        onClick={() => onChange(clamp(servings - 1))}
                        disabled={servings <= 1}
                        aria-label="One fewer serving"
                        className="flex size-11 items-center justify-center rounded-full text-ink transition hover:bg-surface disabled:opacity-35"
                    >
                        <Minus className="size-4" aria-hidden="true" />
                    </button>

                    <span
                        className="min-w-10 text-center font-display text-xl tabular-nums text-ink"
                        aria-live="polite"
                        aria-label={`${servings} ${label}`}
                    >
                        {servings}
                    </span>

                    <button
                        type="button"
                        onClick={() => onChange(clamp(servings + 1))}
                        disabled={servings >= 99}
                        aria-label="One more serving"
                        className="flex size-11 items-center justify-center rounded-full text-ink transition hover:bg-surface disabled:opacity-35"
                    >
                        <Plus className="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>

            {!compact && baseServings > 0 && (
                <div className="flex gap-2" role="group" aria-label="Scale the recipe">
                    {PRESETS.map((preset) => {
                        const target = clamp(baseServings * preset)
                        const selected = Math.abs(factor - preset) < 0.01

                        return (
                            <button
                                key={preset}
                                type="button"
                                onClick={() => onChange(target)}
                                aria-pressed={selected}
                                className={cn(
                                    'h-10 flex-1 rounded-xl text-[0.9rem] font-medium tabular-nums transition active:scale-[0.98]',
                                    selected
                                        ? 'bg-brand text-ink-inverse'
                                        : 'bg-surface-2 text-ink-muted hover:bg-surface-3 hover:text-ink',
                                )}
                            >
                                {preset === 1 ? '1×' : `${preset}×`}
                            </button>
                        )
                    })}
                </div>
            )}
        </div>
    )
}
