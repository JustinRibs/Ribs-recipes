import { Monitor, Moon, Sun } from 'lucide-react'
import { useTheme, type ThemeMode } from '@/hooks/useTheme'
import { cn } from '@/lib/cn'

const OPTIONS: { mode: ThemeMode; label: string; Icon: typeof Sun }[] = [
    { mode: 'light', label: 'Light', Icon: Sun },
    { mode: 'dark', label: 'Dark', Icon: Moon },
    { mode: 'system', label: 'System', Icon: Monitor },
]

/**
 * A three-way segmented control rather than a toggle, because "follow the
 * system" is a real preference and not the absence of one.
 */
export function ThemeToggle({ className }: { className?: string }) {
    const { mode, setMode } = useTheme()

    return (
        <div
            role="radiogroup"
            aria-label="Colour theme"
            className={cn('inline-flex rounded-full bg-surface-2 p-0.5', className)}
        >
            {OPTIONS.map(({ mode: option, label, Icon }) => (
                <button
                    key={option}
                    type="button"
                    role="radio"
                    aria-checked={mode === option}
                    aria-label={label}
                    title={label}
                    onClick={() => setMode(option)}
                    className={cn(
                        'flex size-10 items-center justify-center rounded-full transition duration-150',
                        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-adriatic',
                        mode === option
                            ? 'bg-surface text-ink shadow-[var(--shadow-card)]'
                            : 'text-ink-faint hover:text-ink',
                    )}
                >
                    <Icon className="size-4" aria-hidden="true" />
                </button>
            ))}
        </div>
    )
}
