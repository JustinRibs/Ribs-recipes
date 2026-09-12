import { router } from '@inertiajs/react'
import { ArrowRight, Loader2, Search, X } from 'lucide-react'
import { useCallback, useEffect, useId, useRef, useState } from 'react'
import { cn } from '@/lib/cn'
import { publicRoutes, withQuery } from '@/lib/routes'

interface Suggestion {
    id: number
    title: string
    url: string
    category: string | null
    thumb: string | null
}

interface SearchOverlayProps {
    open: boolean
    onClose: () => void
}

/**
 * Full-screen search.
 *
 * Suggestions come from a small read-only JSON endpoint, debounced and with
 * in-flight requests aborted, so typing stays responsive on a phone. Arrow
 * keys move through results and Enter opens the highlighted one — or runs a
 * full search when nothing is highlighted.
 *
 * The overlay unmounts when it closes rather than hiding itself, so the term
 * and results reset for free — no effect watching `open` to clear them.
 */
export function SearchOverlay({ open, onClose }: SearchOverlayProps) {
    if (!open) return null

    return <SearchPanel onClose={onClose} />
}

function SearchPanel({ onClose }: { onClose: () => void }) {
    const [term, setTerm] = useState('')
    const [results, setResults] = useState<Suggestion[]>([])
    const [loading, setLoading] = useState(false)
    const [active, setActive] = useState(-1)
    const inputRef = useRef<HTMLInputElement>(null)
    const listId = useId()

    useEffect(() => {
        // Delay just past the open transition so iOS reliably raises the
        // keyboard and does not scroll the page behind the overlay.
        const focus = window.setTimeout(() => inputRef.current?.focus(), 90)
        document.body.style.overflow = 'hidden'

        return () => {
            window.clearTimeout(focus)
            document.body.style.overflow = ''
        }
    }, [])

    useEffect(() => {
        const trimmed = term.trim()

        // One letter is not a search; results for shorter terms are simply
        // not shown (see `visible` below) rather than cleared through state.
        if (trimmed.length < 2) return

        const controller = new AbortController()

        const timeout = window.setTimeout(async () => {
            setLoading(true)

            try {
                const response = await fetch(withQuery(publicRoutes.searchSuggest, { q: trimmed }), {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                })

                if (!response.ok) throw new Error('search failed')

                const body = (await response.json()) as { results: Suggestion[] }
                setResults(body.results)
                setActive(-1)
            } catch (error) {
                if ((error as Error).name !== 'AbortError') setResults([])
            } finally {
                setLoading(false)
            }
        }, 160)

        return () => {
            window.clearTimeout(timeout)
            controller.abort()
        }
    }, [term])

    // Derived rather than stored: a term too short to search simply shows
    // nothing, without an effect racing to empty the list.
    const trimmed = term.trim()
    const visible = trimmed.length >= 2 ? results : []

    const submit = useCallback(
        (url?: string) => {
            onClose()
            router.visit(url ?? withQuery(publicRoutes.recipes, { q: term.trim() }))
        },
        [onClose, term],
    )

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Escape') {
            event.preventDefault()
            onClose()
            return
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault()
            setActive((current) => {
                if (visible.length === 0) return -1

                const next = event.key === 'ArrowDown' ? current + 1 : current - 1

                return (next + visible.length) % visible.length
            })
            return
        }

        if (event.key === 'Enter') {
            event.preventDefault()
            const chosen = active >= 0 ? visible[active] : undefined
            if (chosen || term.trim().length > 0) submit(chosen?.url)
        }
    }

    return (
        <div
            className="fixed inset-0 z-50 flex flex-col bg-canvas/92 backdrop-blur-xl"
            role="dialog"
            aria-modal="true"
            aria-label="Search recipes"
        >
            <div className="safe-top shell gutter w-full pt-3">
                <div className="flex items-center gap-2 py-2">
                    <div className="relative flex-1">
                        <Search
                            className="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-ink-faint"
                            aria-hidden="true"
                        />
                        <input
                            ref={inputRef}
                            type="search"
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder="What are we cooking?"
                            enterKeyHint="search"
                            autoComplete="off"
                            autoCorrect="off"
                            spellCheck={false}
                            aria-label="Search recipes"
                            aria-controls={listId}
                            aria-expanded={visible.length > 0}
                            className="h-14 w-full rounded-2xl bg-surface pl-12 pr-11 text-[1.05rem] text-ink shadow-[var(--shadow-card)] ring-1 ring-line-strong placeholder:text-ink-faint focus:ring-2 focus:ring-adriatic focus:outline-none"
                        />
                        {loading && (
                            <Loader2
                                className="absolute right-4 top-1/2 size-4 -translate-y-1/2 animate-spin text-ink-faint"
                                aria-hidden="true"
                            />
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="flex h-14 items-center rounded-2xl px-3 text-[0.95rem] font-medium text-ink-muted transition hover:text-ink"
                    >
                        <X className="size-5 sm:hidden" aria-hidden="true" />
                        <span className="sr-only sm:not-sr-only">Cancel</span>
                    </button>
                </div>
            </div>

            <div className="shell gutter min-h-0 w-full flex-1 overflow-y-auto overscroll-contain pb-10">
                {trimmed.length >= 2 && !loading && visible.length === 0 && (
                    <p className="px-1 py-10 text-center text-ink-muted">Nothing matched “{trimmed}”.</p>
                )}

                <ul id={listId} role="listbox" className="divide-y divide-line">
                    {visible.map((result, index) => (
                        <li key={result.id}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={index === active}
                                onClick={() => submit(result.url)}
                                onMouseEnter={() => setActive(index)}
                                className={cn(
                                    'flex w-full items-center gap-3.5 rounded-xl px-2 py-3 text-left transition',
                                    index === active ? 'bg-surface-2' : 'hover:bg-surface-2',
                                )}
                            >
                                {result.thumb ? (
                                    <img
                                        src={result.thumb}
                                        alt=""
                                        loading="lazy"
                                        className="size-14 shrink-0 rounded-xl object-cover"
                                    />
                                ) : (
                                    <span
                                        className="size-14 shrink-0 rounded-xl bg-surface-2"
                                        aria-hidden="true"
                                    />
                                )}

                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium text-ink">
                                        {result.title}
                                    </span>
                                    {result.category && (
                                        <span className="block text-sm text-ink-muted">
                                            {result.category}
                                        </span>
                                    )}
                                </span>

                                <ArrowRight className="size-4 shrink-0 text-ink-faint" aria-hidden="true" />
                            </button>
                        </li>
                    ))}
                </ul>

                {trimmed.length > 0 && (
                    <button
                        type="button"
                        onClick={() => submit()}
                        className="mt-4 flex w-full items-center justify-between rounded-xl bg-surface-2 px-4 py-3.5 text-left text-[0.95rem] font-medium text-ink transition hover:bg-surface-3"
                    >
                        See all results for “{trimmed}”
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </button>
                )}
            </div>
        </div>
    )
}
