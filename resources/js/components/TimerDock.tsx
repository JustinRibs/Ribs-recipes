import { Bell, Timer, X } from 'lucide-react'
import { cn } from '@/lib/cn'
import { formatClock } from '@/lib/time'
import { useTimers } from '@/hooks/useTimers'

/**
 * Running timers, docked above the fold wherever you are in the app.
 *
 * Timers keep running while you move between steps, between recipes, or away
 * from the recipe entirely, so this follows you rather than living inside
 * cooking mode.
 */
export function TimerDock({ className }: { className?: string }) {
    const { timers, cancel, dismiss } = useTimers()

    if (timers.length === 0) return null

    return (
        <div
            className={cn(
                'pointer-events-none fixed inset-x-0 bottom-0 z-40 flex flex-col items-center gap-2 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]',
                className,
            )}
            aria-live="polite"
        >
            {timers.map((timer) => (
                <div
                    key={timer.id}
                    className={cn(
                        'pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-2xl px-4 py-3 shadow-[var(--shadow-overlay)] ring-1 backdrop-blur-xl transition',
                        timer.done
                            ? 'bg-croatia text-white ring-croatia motion-safe:animate-pulse'
                            : 'bg-surface/92 text-ink ring-line-strong',
                    )}
                >
                    {timer.done ? (
                        <Bell className="size-5 shrink-0" aria-hidden="true" />
                    ) : (
                        <Timer className="size-5 shrink-0 text-adriatic" aria-hidden="true" />
                    )}

                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{timer.label}</p>
                        <p
                            className={cn(
                                'font-display text-xl tabular-nums leading-tight',
                                timer.done ? 'text-white' : 'text-ink',
                            )}
                        >
                            {timer.done ? 'Time’s up' : formatClock(timer.remaining)}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => (timer.done ? dismiss(timer.id) : cancel(timer.id))}
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-full transition',
                            timer.done ? 'hover:bg-white/20' : 'hover:bg-surface-2',
                        )}
                        aria-label={
                            timer.done
                                ? `Dismiss timer for ${timer.label}`
                                : `Cancel timer for ${timer.label}`
                        }
                    >
                        <X className="size-4" aria-hidden="true" />
                    </button>
                </div>
            ))}
        </div>
    )
}
