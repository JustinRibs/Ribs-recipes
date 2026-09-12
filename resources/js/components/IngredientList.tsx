import { Check } from 'lucide-react'
import { cn } from '@/lib/cn'
import { agreeUnit, parseQuantity, scaleQuantity } from '@/lib/quantity'
import type { Ingredient } from '@/types'

interface IngredientListProps {
    ingredients: Ingredient[]
    factor: number
    checked: number[]
    onToggle: (id: number) => void
    className?: string
    /** Larger text and targets, for cooking mode. */
    large?: boolean
}

/**
 * The ingredient list, with scaled amounts and a gathered/not-gathered state.
 *
 * Each row is a real checkbox, so it announces correctly and works from the
 * keyboard; the whole row is the target, because a 16px checkbox is not
 * something you hit with a wet thumb.
 */
export function IngredientList({
    ingredients,
    factor,
    checked,
    onToggle,
    className,
    large = false,
}: IngredientListProps) {
    return (
        <ul className={cn('divide-y divide-line', className)}>
            {ingredients.map((ingredient) => {
                const isChecked = checked.includes(ingredient.id)
                const amount = scaleQuantity(ingredient, factor)
                // "1 cup" doubled should read "2 cups", not "2 cup".
                const unit = agreeUnit(ingredient.unit, parseQuantity(amount))

                return (
                    <li key={ingredient.id}>
                        <label
                            className={cn(
                                'flex cursor-pointer items-start gap-3.5 py-3 transition-opacity',
                                large ? 'py-4 text-[1.08rem]' : 'text-[0.98rem]',
                                isChecked && 'opacity-45',
                            )}
                        >
                            <input
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => onToggle(ingredient.id)}
                                className="peer sr-only"
                            />

                            <span
                                aria-hidden="true"
                                className={cn(
                                    'mt-0.5 flex shrink-0 items-center justify-center rounded-md border-2 transition',
                                    large ? 'size-7' : 'size-6',
                                    'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-adriatic',
                                    isChecked
                                        ? 'border-adriatic bg-adriatic text-white'
                                        : 'border-line-strong bg-surface',
                                )}
                            >
                                {isChecked && (
                                    <Check className={large ? 'size-4' : 'size-3.5'} strokeWidth={3} />
                                )}
                            </span>

                            <span className={cn('min-w-0 flex-1 leading-snug', isChecked && 'line-through')}>
                                {(amount || unit) && (
                                    <span className="font-semibold tabular-nums text-ink">
                                        {[amount, unit].filter(Boolean).join(' ')}{' '}
                                    </span>
                                )}
                                <span className="text-ink">{ingredient.name}</span>
                                {ingredient.note && (
                                    <span className="text-ink-muted">, {ingredient.note}</span>
                                )}
                            </span>
                        </label>
                    </li>
                )
            })}
        </ul>
    )
}
