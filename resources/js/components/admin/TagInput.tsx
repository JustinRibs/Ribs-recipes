import { X } from 'lucide-react'
import { useId, useMemo, useState } from 'react'
import { cn } from '@/lib/cn'

interface TagInputProps {
    value: string[]
    onChange: (tags: string[]) => void
    suggestions: string[]
    label?: string
    hint?: string
    error?: string | null
    max?: number
}

/**
 * Tag entry with suggestions.
 *
 * Typing and pressing Enter (or comma) adds a tag, Backspace on an empty field
 * removes the last one, and existing tags are offered as you type so the same
 * idea does not end up spelled three ways.
 */
export function TagInput({
    value,
    onChange,
    suggestions,
    label = 'Tags',
    hint,
    error,
    max = 20,
}: TagInputProps) {
    const [draft, setDraft] = useState('')
    const id = useId()

    const matches = useMemo(() => {
        const query = draft.trim().toLowerCase()
        const taken = new Set(value.map((tag) => tag.toLowerCase()))

        return suggestions
            .filter((tag) => !taken.has(tag.toLowerCase()))
            .filter((tag) => (query ? tag.toLowerCase().includes(query) : true))
            .slice(0, query ? 6 : 8)
    }, [draft, suggestions, value])

    const add = (tag: string) => {
        const trimmed = tag.trim().replace(/,+$/, '')

        if (!trimmed || value.length >= max) return
        if (value.some((existing) => existing.toLowerCase() === trimmed.toLowerCase())) {
            setDraft('')
            return
        }

        onChange([...value, trimmed])
        setDraft('')
    }

    return (
        <div className="space-y-1.5">
            <label htmlFor={id} className="block text-sm font-medium text-ink">
                {label}
            </label>

            <div
                className={cn(
                    'flex min-h-11 flex-wrap items-center gap-1.5 rounded-xl bg-surface p-1.5 ring-1 transition focus-within:ring-2 focus-within:ring-adriatic',
                    error ? 'ring-croatia' : 'ring-line-strong',
                )}
            >
                {value.map((tag) => (
                    <span
                        key={tag}
                        className="inline-flex min-h-10 items-center gap-1 rounded-full bg-surface-2 py-1 pl-3 pr-1 text-[0.85rem] font-medium text-ink"
                    >
                        {tag}
                        <button
                            type="button"
                            onClick={() => onChange(value.filter((item) => item !== tag))}
                            aria-label={`Remove ${tag}`}
                            className="flex size-8 items-center justify-center rounded-full text-ink-muted transition hover:bg-surface-3 hover:text-ink"
                        >
                            <X className="size-3.5" aria-hidden="true" />
                        </button>
                    </span>
                ))}

                <input
                    id={id}
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ',') {
                            event.preventDefault()
                            add(draft)
                        }

                        if (event.key === 'Backspace' && draft === '' && value.length > 0) {
                            onChange(value.slice(0, -1))
                        }
                    }}
                    onBlur={() => add(draft)}
                    placeholder={value.length === 0 ? 'Add a tag…' : ''}
                    aria-describedby={error ? `${id}-error` : undefined}
                    className="h-9 min-w-28 flex-1 bg-transparent px-2 text-[0.95rem] text-ink placeholder:text-ink-faint focus:outline-none"
                />
            </div>

            {matches.length > 0 && (
                <div className="flex flex-wrap gap-1.5 pt-1">
                    {matches.map((tag) => (
                        <button
                            key={tag}
                            type="button"
                            onClick={() => add(tag)}
                            className="inline-flex min-h-10 items-center rounded-full bg-surface-2 px-3.5 text-[0.82rem] text-ink-muted transition hover:bg-surface-3 hover:text-ink"
                        >
                            + {tag}
                        </button>
                    ))}
                </div>
            )}

            {error ? (
                <p id={`${id}-error`} className="text-sm text-croatia" role="alert">
                    {error}
                </p>
            ) : hint ? (
                <p className="text-sm text-ink-muted">{hint}</p>
            ) : null}
        </div>
    )
}
