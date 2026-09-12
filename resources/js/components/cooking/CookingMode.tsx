import { Check, ChevronLeft, ChevronRight, ListChecks, Lightbulb, Timer, X } from 'lucide-react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { IngredientList } from '@/components/IngredientList'
import { ServingScaler } from '@/components/ServingScaler'
import { Sheet } from '@/components/ui/Sheet'
import { cn } from '@/lib/cn'
import { detectDurations, timerLabel } from '@/lib/time'
import { useSwipe } from '@/hooks/useSwipe'
import { useTimers } from '@/hooks/useTimers'
import { useWakeLock } from '@/hooks/useWakeLock'
import type { Ingredient, Step } from '@/types'

interface CookingModeProps {
    title: string
    steps: Step[]
    ingredients: Ingredient[]
    servings: number
    baseServings: number
    servingsLabel: string
    onServingsChange: (servings: number) => void
    checkedIngredients: number[]
    onToggleIngredient: (id: number) => void
    completedSteps: number[]
    onToggleStep: (id: number) => void
    onExit: () => void
}

/**
 * Cooking mode.
 *
 * One step at a time, at a size that reads from across a counter. Swipe or tap
 * to move; the screen stays awake; timers start from the step that mentions
 * them and keep running while you move on. Ingredients are one tap away and
 * stay scaled to whatever serving count was chosen on the recipe page.
 *
 * Accidental exits are guarded: the close button asks before leaving once any
 * progress exists, and the browser's own back/refresh gets the standard
 * "leave site?" prompt for the same reason.
 */
export function CookingMode({
    title,
    steps,
    ingredients,
    servings,
    baseServings,
    servingsLabel,
    onServingsChange,
    checkedIngredients,
    onToggleIngredient,
    completedSteps,
    onToggleStep,
    onExit,
}: CookingModeProps) {
    const [index, setIndex] = useState(0)
    const [ingredientsOpen, setIngredientsOpen] = useState(false)
    const [confirmExit, setConfirmExit] = useState(false)

    const { start: startTimer } = useTimers()
    const wakeLock = useWakeLock(true)

    const step = steps[index]
    const factor = baseServings > 0 ? servings / baseServings : 1
    const progress = steps.length > 0 ? ((index + 1) / steps.length) * 100 : 0
    const hasProgress = completedSteps.length > 0 || index > 0

    const go = useCallback(
        (delta: number) => {
            setIndex((current) => Math.min(steps.length - 1, Math.max(0, current + delta)))
        },
        [steps.length],
    )

    const swipe = useSwipe({ onSwipeLeft: () => go(1), onSwipeRight: () => go(-1) })

    // Explicit timers win; detected ones are offered as a convenience.
    const timers = useMemo(() => {
        if (!step) return []

        if (step.timerSeconds) {
            return [{ seconds: step.timerSeconds, label: timerLabel(step.timerSeconds), detected: false }]
        }

        return detectDurations(step.instruction).map((found) => ({
            seconds: found.seconds,
            label: timerLabel(found.seconds),
            detected: true,
        }))
    }, [step])

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowRight' || event.key === ' ') {
                event.preventDefault()
                go(1)
            }
            if (event.key === 'ArrowLeft') go(-1)
            if (event.key === 'Escape') setConfirmExit(true)
        }

        window.addEventListener('keydown', onKey)
        return () => window.removeEventListener('keydown', onKey)
    }, [go])

    // Warn before a refresh or back-swipe throws away a half-cooked session.
    useEffect(() => {
        if (!hasProgress) return

        const onBeforeUnload = (event: BeforeUnloadEvent) => event.preventDefault()

        window.addEventListener('beforeunload', onBeforeUnload)
        return () => window.removeEventListener('beforeunload', onBeforeUnload)
    }, [hasProgress])

    useEffect(() => {
        document.body.style.overflow = 'hidden'
        return () => {
            document.body.style.overflow = ''
        }
    }, [])

    const exit = () => (hasProgress ? setConfirmExit(true) : onExit())

    if (!step) return null

    const done = completedSteps.includes(step.id)
    const isLast = index === steps.length - 1

    return (
        <div
            className="fixed inset-0 z-50 flex flex-col bg-canvas"
            role="dialog"
            aria-modal="true"
            aria-label={`Cooking ${title}`}
        >
            {/* ---------------------------------------------------------------
                Header: progress, step counter, exit.
            --------------------------------------------------------------- */}
            <header className="safe-top shrink-0 border-b border-line">
                <div
                    className="h-1 bg-surface-2"
                    role="progressbar"
                    aria-valuenow={index + 1}
                    aria-valuemin={1}
                    aria-valuemax={steps.length}
                    aria-label="Cooking progress"
                >
                    <div
                        className="h-full bg-adriatic transition-[width] duration-300 ease-[var(--ease-out-soft)]"
                        style={{ width: `${progress}%` }}
                    />
                </div>

                <div className="flex items-center gap-3 px-4 py-3 short:py-2">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-[0.78rem] font-medium uppercase tracking-[0.12em] text-ink-faint">
                            {title}
                        </p>
                        <p className="font-display text-lg leading-tight text-ink" aria-live="polite">
                            Step {index + 1} of {steps.length}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setIngredientsOpen(true)}
                        aria-label="Ingredients"
                        className="flex h-11 items-center gap-2 rounded-full bg-surface-2 px-4 text-[0.9rem] font-medium text-ink transition hover:bg-surface-3"
                    >
                        <ListChecks className="size-4" aria-hidden="true" />
                        <span className="hidden min-[360px]:inline">Ingredients</span>
                    </button>

                    <button
                        type="button"
                        onClick={exit}
                        aria-label="Leave cooking mode"
                        className="flex size-11 shrink-0 items-center justify-center rounded-full text-ink-muted transition hover:bg-surface-2 hover:text-ink"
                    >
                        <X className="size-5" aria-hidden="true" />
                    </button>
                </div>
            </header>

            {/* ---------------------------------------------------------------
                The step itself.
            --------------------------------------------------------------- */}
            <div
                className="min-h-0 flex-1 overflow-y-auto overscroll-contain [touch-action:pan-y]"
                {...swipe}
            >
                <div className="mx-auto flex min-h-full w-full max-w-2xl flex-col gap-6 px-5 py-8 short:gap-3 short:py-4 sm:px-8 sm:py-12">
                    {step.image && (
                        <img
                            src={step.image.src}
                            srcSet={step.image.srcset ?? undefined}
                            sizes="(min-width: 640px) 42rem, 92vw"
                            alt={step.image.alt ?? ''}
                            className="w-full rounded-2xl object-cover"
                        />
                    )}

                    <p
                        key={step.id}
                        className={cn(
                            'font-display leading-[1.22] tracking-[-0.015em] text-ink transition-opacity duration-200',
                            // Large enough to read at arm's length, and it
                            // steps down only when the text is genuinely long.
                            step.instruction.length > 220
                                ? 'text-[1.65rem] short:text-[1.3rem] sm:text-[2rem]'
                                : 'text-[2.05rem] short:text-[1.55rem] sm:text-[2.6rem]',
                        )}
                    >
                        {step.instruction}
                    </p>

                    {timers.length > 0 && (
                        <div className="flex flex-wrap gap-2.5">
                            {timers.map((timer) => (
                                <button
                                    key={timer.seconds}
                                    type="button"
                                    onClick={() => startTimer(timer.seconds, `${title} — step ${index + 1}`)}
                                    className="inline-flex h-12 items-center gap-2.5 rounded-full bg-adriatic px-5 text-[0.98rem] font-semibold text-white shadow-[var(--shadow-card)] transition active:scale-[0.98] short:h-10"
                                >
                                    <Timer className="size-4.5" aria-hidden="true" />
                                    Start {timer.label} timer
                                </button>
                            ))}
                        </div>
                    )}

                    {timers.some((timer) => timer.detected) && (
                        <p className="flex items-start gap-2 text-[0.85rem] text-ink-faint">
                            <Lightbulb className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                            Timer suggested from the step text.
                        </p>
                    )}

                    <div className="mt-auto pt-4 short:pt-1">
                        <button
                            type="button"
                            onClick={() => onToggleStep(step.id)}
                            aria-pressed={done}
                            className={cn(
                                'inline-flex h-12 items-center gap-2.5 rounded-full px-5 text-[0.95rem] font-medium transition active:scale-[0.98] short:h-10',
                                done
                                    ? 'bg-olive text-white'
                                    : 'bg-surface-2 text-ink-muted hover:bg-surface-3 hover:text-ink',
                            )}
                        >
                            <Check className="size-4.5" aria-hidden="true" />
                            {done ? 'Step done' : 'Mark step done'}
                        </button>
                    </div>
                </div>
            </div>

            {/* ---------------------------------------------------------------
                Footer: thumb-sized navigation, clear of the home indicator.
            --------------------------------------------------------------- */}
            <nav className="safe-dock shrink-0 border-t border-line bg-surface px-4 pt-3" aria-label="Steps">
                <div className="mx-auto flex w-full max-w-2xl items-center gap-3">
                    <button
                        type="button"
                        onClick={() => go(-1)}
                        disabled={index === 0}
                        className="flex h-14 w-16 items-center justify-center rounded-2xl bg-surface-2 text-ink transition active:scale-[0.97] disabled:opacity-30 short:h-12"
                        aria-label="Previous step"
                    >
                        <ChevronLeft className="size-6" aria-hidden="true" />
                    </button>

                    {isLast ? (
                        <button
                            type="button"
                            onClick={() => {
                                onToggleStep(step.id)
                                onExit()
                            }}
                            className="flex h-14 flex-1 items-center justify-center gap-2 rounded-2xl bg-olive text-[1.05rem] font-semibold text-white transition active:scale-[0.98] short:h-12"
                        >
                            <Check className="size-5" aria-hidden="true" />
                            Finish
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                if (!done) onToggleStep(step.id)
                                go(1)
                            }}
                            className="flex h-14 flex-1 items-center justify-center gap-2 rounded-2xl bg-brand text-[1.05rem] font-semibold text-ink-inverse transition active:scale-[0.98] short:h-12"
                        >
                            Next step
                            <ChevronRight className="size-5" aria-hidden="true" />
                        </button>
                    )}
                </div>

                {!wakeLock.supported && (
                    <p className="mx-auto mt-2 max-w-2xl text-center text-[0.72rem] text-ink-faint">
                        This browser cannot keep the screen awake — set your auto-lock longer while you cook.
                    </p>
                )}
            </nav>

            <Sheet open={ingredientsOpen} onClose={() => setIngredientsOpen(false)} title="Ingredients">
                {baseServings > 0 && (
                    <ServingScaler
                        baseServings={baseServings}
                        servings={servings}
                        label={servingsLabel}
                        onChange={onServingsChange}
                        compact
                        className="mb-4 border-b border-line pb-4"
                    />
                )}

                <IngredientList
                    ingredients={ingredients}
                    factor={factor}
                    checked={checkedIngredients}
                    onToggle={onToggleIngredient}
                    large
                />
            </Sheet>

            {confirmExit && (
                <ExitConfirm
                    onStay={() => setConfirmExit(false)}
                    onLeave={() => {
                        setConfirmExit(false)
                        onExit()
                    }}
                />
            )}
        </div>
    )
}

function ExitConfirm({ onStay, onLeave }: { onStay: () => void; onLeave: () => void }) {
    return (
        <div
            className="absolute inset-0 z-10 flex items-end justify-center bg-black/50 p-4 sm:items-center"
            role="alertdialog"
            aria-modal="true"
            aria-label="Leave cooking mode?"
        >
            <div className="safe-bottom w-full max-w-sm rounded-3xl bg-surface p-6 shadow-[var(--shadow-overlay)]">
                <h2 className="font-display text-2xl tracking-tight text-ink">Leave cooking mode?</h2>
                <p className="mt-2 text-[0.95rem] leading-relaxed text-ink-muted">
                    Your checked ingredients and finished steps are kept — any running timers keep running
                    too.
                </p>

                <div className="mt-6 flex flex-col gap-2">
                    <button
                        type="button"
                        onClick={onStay}
                        className="h-12 rounded-2xl bg-brand text-[0.98rem] font-semibold text-ink-inverse transition active:scale-[0.98]"
                    >
                        Keep cooking
                    </button>
                    <button
                        type="button"
                        onClick={onLeave}
                        className="h-12 rounded-2xl bg-surface-2 text-[0.98rem] font-medium text-ink transition active:scale-[0.98]"
                    >
                        Leave
                    </button>
                </div>
            </div>
        </div>
    )
}
