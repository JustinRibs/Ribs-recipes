import { Link, router } from '@inertiajs/react'
import { Check, Search, SlidersHorizontal, X } from 'lucide-react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { RecipeCard, RecipeCardSkeleton } from '@/components/RecipeCard'
import { Button } from '@/components/ui/Button'
import { Sheet } from '@/components/ui/Sheet'
import { cn } from '@/lib/cn'
import { withQuery } from '@/lib/routes'
import { PublicLayout } from '@/layouts/PublicLayout'
import type { BrowseFilters, Facets, Paginated, RecipeCardData } from '@/types'

interface BrowseProps {
    recipes: Paginated<RecipeCardData>
    filters: BrowseFilters
    facets: Facets
    heading: string
    lede?: string | null
    lockedFilter?: { type: 'category' | 'tag'; name: string; slug: string } | null
}

const SORTS = [
    { value: 'newest', label: 'Newest' },
    { value: 'relevance', label: 'Best match' },
    { value: 'quickest', label: 'Quickest' },
    { value: 'title', label: 'A–Z' },
    { value: 'oldest', label: 'Oldest' },
] as const

export default function RecipesIndex({ recipes, filters, facets, heading, lede, lockedFilter }: BrowseProps) {
    const [term, setTerm] = useState(filters.q ?? '')
    const [panelOpen, setPanelOpen] = useState(false)
    const [loading, setLoading] = useState(false)

    // The field is local so typing stays instant, but it must also follow the
    // server when a filter is cleared from elsewhere on the page. Adjusting
    // during render is React's own pattern for that, and it avoids the extra
    // render — and the flash of the previous term — an effect would cause.
    const [serverTerm, setServerTerm] = useState(filters.q ?? '')

    if (serverTerm !== (filters.q ?? '')) {
        setServerTerm(filters.q ?? '')
        setTerm(filters.q ?? '')
    }

    // The base path stays put, so a category page keeps its own URL while its
    // filters change.
    const basePath = useMemo(() => window.location.pathname, [])

    const visit = useCallback(
        (next: Partial<BrowseFilters>) => {
            const merged = { ...filters, ...next }

            router.get(
                withQuery(basePath, {
                    q: merged.q ?? '',
                    // A locked filter belongs to the page, not the query string.
                    category: lockedFilter?.type === 'category' ? '' : (merged.category ?? ''),
                    tags: lockedFilter?.type === 'tag' ? [] : merged.tags,
                    sort: merged.sort === 'newest' ? '' : merged.sort,
                    favorites: merged.favorites,
                }),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    onStart: () => setLoading(true),
                    onFinish: () => setLoading(false),
                },
            )
        },
        [basePath, filters, lockedFilter],
    )

    // Debounced search-as-you-type against the server.
    useEffect(() => {
        const current = filters.q ?? ''
        if (term === current) return

        const timeout = window.setTimeout(() => visit({ q: term.trim() || null }), 320)
        return () => window.clearTimeout(timeout)
    }, [term, filters.q, visit])

    const toggleTag = (slug: string) => {
        const next = filters.tags.includes(slug)
            ? filters.tags.filter((tag) => tag !== slug)
            : [...filters.tags, slug]

        visit({ tags: next })
    }

    const activeCount =
        (filters.category && lockedFilter?.type !== 'category' ? 1 : 0) +
        (lockedFilter?.type === 'tag' ? 0 : filters.tags.length) +
        (filters.favorites ? 1 : 0)

    const clearAll = () => visit({ category: null, tags: [], favorites: false, q: null })

    return (
        <PublicLayout>
            <div className="shell gutter pb-16 pt-8 sm:pt-12">
                <header>
                    <h1 className="font-display text-[clamp(2.25rem,7vw,3.25rem)] leading-[1.02] tracking-[-0.022em] text-ink">
                        {heading}
                    </h1>
                    {lede && (
                        <p className="mt-3 max-w-xl text-[1.02rem] leading-relaxed text-ink-muted">{lede}</p>
                    )}
                </header>

                {/* Sticky control bar — stays reachable while scrolling a long
                    grid, which matters most on a phone. */}
                <div className="sticky top-16 z-20 -mx-[clamp(1rem,4vw,2.5rem)] mt-6 bg-canvas/92 px-[clamp(1rem,4vw,2.5rem)] py-3 backdrop-blur-xl">
                    <div className="flex items-center gap-2">
                        <div className="relative flex-1">
                            <Search
                                className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint"
                                aria-hidden="true"
                            />
                            <input
                                type="search"
                                value={term}
                                onChange={(event) => setTerm(event.target.value)}
                                placeholder="Search this collection"
                                aria-label="Search recipes"
                                enterKeyHint="search"
                                className="h-11 w-full rounded-full bg-surface pl-10 pr-4 text-[0.95rem] text-ink ring-1 ring-line-strong placeholder:text-ink-faint focus:ring-2 focus:ring-adriatic focus:outline-none"
                            />
                        </div>

                        <button
                            type="button"
                            onClick={() => setPanelOpen(true)}
                            aria-label="Filter recipes"
                            aria-expanded={panelOpen}
                            className={cn(
                                'flex h-11 shrink-0 items-center gap-2 rounded-full px-4 text-[0.92rem] font-medium transition',
                                activeCount > 0
                                    ? 'bg-brand text-ink-inverse'
                                    : 'bg-surface text-ink ring-1 ring-line-strong hover:bg-surface-2',
                            )}
                        >
                            <SlidersHorizontal className="size-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Filters</span>
                            {activeCount > 0 && (
                                <span className="flex size-5 items-center justify-center rounded-full bg-white/20 text-xs tabular-nums">
                                    {activeCount}
                                </span>
                            )}
                        </button>
                    </div>

                    {activeCount > 0 && (
                        <div className="mt-2.5 flex flex-wrap items-center gap-2">
                            {filters.category && lockedFilter?.type !== 'category' && (
                                <FilterChip
                                    label={
                                        facets.categories.find((c) => c.slug === filters.category)?.name ??
                                        filters.category
                                    }
                                    onRemove={() => visit({ category: null })}
                                />
                            )}
                            {lockedFilter?.type !== 'tag' &&
                                filters.tags.map((slug) => (
                                    <FilterChip
                                        key={slug}
                                        label={facets.tags.find((t) => t.slug === slug)?.name ?? slug}
                                        onRemove={() => toggleTag(slug)}
                                    />
                                ))}
                            {filters.favorites && (
                                <FilterChip label="Favorites" onRemove={() => visit({ favorites: false })} />
                            )}
                            <button
                                type="button"
                                onClick={clearAll}
                                className="rounded-full px-2 py-1 text-[0.82rem] font-medium text-ink-muted underline underline-offset-4 transition hover:text-ink"
                            >
                                Clear all
                            </button>
                        </div>
                    )}
                </div>

                <p className="mt-4 text-[0.88rem] text-ink-muted" aria-live="polite">
                    {recipes.meta.total} {recipes.meta.total === 1 ? 'recipe' : 'recipes'}
                    {filters.q ? ` matching “${filters.q}”` : ''}
                </p>

                {loading ? (
                    <div className="mt-6 grid grid-cols-1 gap-x-5 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
                        {Array.from({ length: 6 }, (_, index) => (
                            <RecipeCardSkeleton key={index} />
                        ))}
                    </div>
                ) : recipes.data.length === 0 ? (
                    <EmptyState onClear={activeCount > 0 || filters.q ? clearAll : undefined} />
                ) : (
                    <div className="mt-6 grid grid-cols-1 gap-x-5 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
                        {recipes.data.map((recipe, index) => (
                            <RecipeCard key={recipe.id} recipe={recipe} priority={index < 3} />
                        ))}
                    </div>
                )}

                {recipes.meta.lastPage > 1 && (
                    <nav className="mt-14 flex items-center justify-between gap-4" aria-label="Pagination">
                        {recipes.meta.prevPageUrl ? (
                            <Link
                                href={recipes.meta.prevPageUrl}
                                preserveScroll={false}
                                className="rounded-full bg-surface px-5 py-2.5 text-[0.92rem] font-medium text-ink ring-1 ring-line-strong transition hover:bg-surface-2"
                            >
                                Previous
                            </Link>
                        ) : (
                            <span />
                        )}

                        <span className="text-[0.88rem] tabular-nums text-ink-muted">
                            Page {recipes.meta.currentPage} of {recipes.meta.lastPage}
                        </span>

                        {recipes.meta.nextPageUrl ? (
                            <Link
                                href={recipes.meta.nextPageUrl}
                                preserveScroll={false}
                                className="rounded-full bg-surface px-5 py-2.5 text-[0.92rem] font-medium text-ink ring-1 ring-line-strong transition hover:bg-surface-2"
                            >
                                Next
                            </Link>
                        ) : (
                            <span />
                        )}
                    </nav>
                )}
            </div>

            <Sheet
                open={panelOpen}
                onClose={() => setPanelOpen(false)}
                title="Filter recipes"
                footer={
                    <div className="flex gap-2">
                        <Button variant="secondary" className="flex-1" onClick={clearAll}>
                            Clear all
                        </Button>
                        <Button className="flex-1" onClick={() => setPanelOpen(false)}>
                            Show {recipes.meta.total} {recipes.meta.total === 1 ? 'recipe' : 'recipes'}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-7 pb-2">
                    <fieldset>
                        <legend className="text-sm font-semibold uppercase tracking-[0.1em] text-ink-faint">
                            Sort
                        </legend>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {SORTS.map((sort) => (
                                <OptionChip
                                    key={sort.value}
                                    label={sort.label}
                                    selected={filters.sort === sort.value}
                                    onClick={() => visit({ sort: sort.value })}
                                />
                            ))}
                        </div>
                    </fieldset>

                    {lockedFilter?.type !== 'category' && facets.categories.length > 0 && (
                        <fieldset>
                            <legend className="text-sm font-semibold uppercase tracking-[0.1em] text-ink-faint">
                                Category
                            </legend>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {facets.categories.map((category) => (
                                    <OptionChip
                                        key={category.slug}
                                        label={`${category.name} · ${category.count}`}
                                        selected={filters.category === category.slug}
                                        onClick={() =>
                                            visit({
                                                category:
                                                    filters.category === category.slug ? null : category.slug,
                                            })
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>
                    )}

                    {lockedFilter?.type !== 'tag' && facets.tags.length > 0 && (
                        <fieldset>
                            <legend className="text-sm font-semibold uppercase tracking-[0.1em] text-ink-faint">
                                Tags
                            </legend>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {facets.tags.map((tag) => (
                                    <OptionChip
                                        key={tag.slug}
                                        label={`${tag.name} · ${tag.count}`}
                                        selected={filters.tags.includes(tag.slug)}
                                        onClick={() => toggleTag(tag.slug)}
                                    />
                                ))}
                            </div>
                        </fieldset>
                    )}

                    <fieldset>
                        <legend className="text-sm font-semibold uppercase tracking-[0.1em] text-ink-faint">
                            Only show
                        </legend>
                        <div className="mt-3">
                            <OptionChip
                                label="Favorites"
                                selected={filters.favorites}
                                onClick={() => visit({ favorites: !filters.favorites })}
                            />
                        </div>
                    </fieldset>
                </div>
            </Sheet>
        </PublicLayout>
    )
}

function FilterChip({ label, onRemove }: { label: string; onRemove: () => void }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-surface-2 py-1.5 pl-3 pr-1.5 text-[0.82rem] font-medium text-ink">
            {label}
            <button
                type="button"
                onClick={onRemove}
                aria-label={`Remove ${label} filter`}
                className="flex size-5 items-center justify-center rounded-full text-ink-muted transition hover:bg-surface-3 hover:text-ink"
            >
                <X className="size-3.5" aria-hidden="true" />
            </button>
        </span>
    )
}

function OptionChip({ label, selected, onClick }: { label: string; selected: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={selected}
            className={cn(
                'inline-flex min-h-11 items-center gap-1.5 rounded-full px-4 text-[0.9rem] font-medium transition active:scale-[0.98]',
                selected ? 'bg-brand text-ink-inverse' : 'bg-surface-2 text-ink hover:bg-surface-3',
            )}
        >
            {selected && <Check className="size-3.5" aria-hidden="true" />}
            {label}
        </button>
    )
}

function EmptyState({ onClear }: { onClear?: () => void }) {
    return (
        <div className="mt-16 rounded-card border border-dashed border-line-strong py-16 text-center">
            <h2 className="font-display text-2xl tracking-tight text-ink">Nothing matched</h2>
            <p className="mx-auto mt-2 max-w-sm text-[0.95rem] text-ink-muted">
                Try a different word, or loosen the filters — ingredients and tags are searched too.
            </p>
            {onClear && (
                <Button variant="secondary" className="mt-6" onClick={onClear}>
                    Clear filters
                </Button>
            )}
        </div>
    )
}
