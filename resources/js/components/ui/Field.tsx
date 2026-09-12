import {
    useId,
    type InputHTMLAttributes,
    type ReactNode,
    type SelectHTMLAttributes,
    type TextareaHTMLAttributes,
} from 'react'
import { cn } from '@/lib/cn'

const control =
    'w-full rounded-xl bg-surface px-3.5 py-2.5 text-ink ring-1 ring-line-strong ' +
    'placeholder:text-ink-faint transition ' +
    'focus:ring-2 focus:ring-adriatic focus:outline-none ' +
    'disabled:opacity-50 disabled:bg-surface-2'

interface FieldShellProps {
    label: string
    hint?: ReactNode
    error?: string | null
    required?: boolean
    children: (id: string, describedBy: string | undefined) => ReactNode
    className?: string
}

/**
 * Label + control + hint + error, wired together with the right `id`,
 * `aria-describedby` and `aria-invalid` so screen readers announce the whole
 * thing rather than a bare box.
 */
export function Field({ label, hint, error, required, children, className }: FieldShellProps) {
    const id = useId()
    const hintId = hint ? `${id}-hint` : undefined
    const errorId = error ? `${id}-error` : undefined
    const describedBy = [errorId, hintId].filter(Boolean).join(' ') || undefined

    return (
        <div className={cn('space-y-1.5', className)}>
            <label htmlFor={id} className="block text-sm font-medium text-ink">
                {label}
                {required && (
                    <span className="ml-1 text-croatia" aria-hidden="true">
                        *
                    </span>
                )}
            </label>

            {children(id, describedBy)}

            {error ? (
                <p id={errorId} className="text-sm text-croatia" role="alert">
                    {error}
                </p>
            ) : hint ? (
                <p id={hintId} className="text-sm text-ink-muted">
                    {hint}
                </p>
            ) : null}
        </div>
    )
}

type TextFieldProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> & {
    label: string
    hint?: ReactNode
    error?: string | null
    wrapperClassName?: string
}

export function TextField({
    label,
    hint,
    error,
    wrapperClassName,
    className,
    required,
    ...props
}: TextFieldProps) {
    return (
        <Field label={label} hint={hint} error={error} required={required} className={wrapperClassName}>
            {(id, describedBy) => (
                <input
                    id={id}
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    className={cn(control, error && 'ring-croatia focus:ring-croatia', className)}
                    required={required}
                    {...props}
                />
            )}
        </Field>
    )
}

type TextAreaProps = Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'id'> & {
    label: string
    hint?: ReactNode
    error?: string | null
}

export function TextAreaField({ label, hint, error, className, required, ...props }: TextAreaProps) {
    return (
        <Field label={label} hint={hint} error={error} required={required}>
            {(id, describedBy) => (
                <textarea
                    id={id}
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    className={cn(
                        control,
                        'min-h-28 resize-y leading-relaxed',
                        error && 'ring-croatia',
                        className,
                    )}
                    required={required}
                    {...props}
                />
            )}
        </Field>
    )
}

type SelectProps = Omit<SelectHTMLAttributes<HTMLSelectElement>, 'id'> & {
    label: string
    hint?: ReactNode
    error?: string | null
}

export function SelectField({ label, hint, error, className, children, required, ...props }: SelectProps) {
    return (
        <Field label={label} hint={hint} error={error} required={required}>
            {(id, describedBy) => (
                <select
                    id={id}
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    className={cn(control, 'appearance-none pr-9', error && 'ring-croatia', className)}
                    style={{
                        backgroundImage:
                            "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%238b94a1' stroke-width='1.6' stroke-linecap='round'%3E%3Cpath d='M6 8l4 4 4-4'/%3E%3C/svg%3E\")",
                        backgroundRepeat: 'no-repeat',
                        backgroundPosition: 'right 0.7rem center',
                        backgroundSize: '1.15rem',
                    }}
                    required={required}
                    {...props}
                >
                    {children}
                </select>
            )}
        </Field>
    )
}

export function Switch({
    checked,
    onChange,
    label,
    description,
}: {
    checked: boolean
    onChange: (value: boolean) => void
    label: string
    description?: string
}) {
    // The whole row is the control, not just the 48px pill beside the text.
    // A <label> would not have done it: labels forward clicks to form
    // controls, and this is a button — so tapping the description did nothing.
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            onClick={() => onChange(!checked)}
            className="flex w-full cursor-pointer items-start gap-3 rounded-xl py-2 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-adriatic"
        >
            <span
                aria-hidden="true"
                className={cn(
                    'relative mt-0.5 h-7 w-12 shrink-0 rounded-full transition-colors duration-200',
                    checked ? 'bg-adriatic' : 'bg-surface-3',
                )}
            >
                <span
                    className={cn(
                        'absolute top-1 size-5 rounded-full bg-white shadow-sm transition-transform duration-200 ease-[var(--ease-out-soft)]',
                        checked ? 'translate-x-6' : 'translate-x-1',
                    )}
                />
            </span>

            <span className="min-w-0">
                <span className="block text-sm font-medium text-ink">{label}</span>
                {description && <span className="block text-sm text-ink-muted">{description}</span>}
            </span>
        </button>
    )
}

export { control as controlClasses }
