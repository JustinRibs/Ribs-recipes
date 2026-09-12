import { Link, router } from '@inertiajs/react'
import { Copy, Eye, Pencil, Plus, RotateCcw, Search, Star, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Button, ButtonLink } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { AdminLayout } from '@/layouts/AdminLayout'
import { cn } from '@/lib/cn'
import { adminRoutes, withQuery } from '@/lib/routes'
import { humaniseMinutes } from '@/lib/time'
import type { AdminRecipeRow, CategoryOption, PaginationMeta } from '@/types'

interface IndexProps {
    recipes: { data: AdminRecipeRow[]; meta: PaginationMeta }
    filters: {
        q: string | null
        category: string | null
        tag: string | null
        status: string | null
        favorites: boolean
        trashed: boolean
        sort: string
    }
    categories: CategoryOption[]
    tags: { id: number; name: string; slug: string }[]
    trashedCount: number
}

export default function AdminRecipesIndex({ recipes, filters, categories, tags, trashedCount }: IndexProps) {
    const [term, setTerm] = useState(filters.q ?? '')
    const [confirm, setConfirm] = useState<{ row: AdminRecipeRow; permanent: boolean } | null>(null)

    // See the note on the public browse page: the field is local for
    // responsiveness, and follows the server when the query changes elsewhere.
    const [serverTerm, setServerTerm] = useState(filters.q ?? '')

    if (serverTerm !== (filters.q ?? '')) {
        setServerTerm(filters.q ?? '')
        setTerm(filters.q ?? '')
    }

    const visit = (next: Partial<IndexProps['filters']>) => {
        router.get(
            withQuery(adminRoutes.recipes, { ...filters, ...next }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        )
    }

    useEffect(() => {
        const current = filters.q ?? ''
        if (term === current) return

        const timeout = window.setTimeout(() => visit({ q: term.trim() || null }), 320)
        return () => window.clearTimeout(timeout)
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term])

    return (
        <AdminLayout
            title="Recipes"
            actions={
                <ButtonLink href={adminRoutes.recipeCreate} size="sm" aria-label="New recipe">
                    <Plus className="size-4" aria-hidden="true" />
                    <span className="hidden sm:inline">New recipe</span>
                </ButtonLink>
            }
        >
            {/* --- Filters -------------------------------------------------- */}
            <div className="space-y-3">
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-faint"
                        aria-hidden="true"
                    />
                    <input
                        type="search"
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        placeholder="Search titles, ingredients, tags"
                        aria-label="Search recipes"
                        className="h-11 w-full rounded-full bg-surface pl-10 pr-4 text-[0.95rem] text-ink ring-1 ring-line-strong placeholder:text-ink-faint focus:ring-2 focus:ring-adriatic focus:outline-none"
                    />
                </div>

                <div className="rail flex gap-2 overflow-x-auto pb-1">
                    <Chip
                        active={!filters.status && !filters.favorites && !filters.trashed}
                        onClick={() => visit({ status: null, favorites: false, trashed: false })}
                    >
                        All
                    </Chip>
                    <Chip
                        active={filters.status === 'published'}
                        onClick={() =>
                            visit({
                                status: filters.status === 'published' ? null : 'published',
                                trashed: false,
                            })
                        }
                    >
                        Published
                    </Chip>
                    <Chip
                        active={filters.status === 'draft'}
                        onClick={() =>
                            visit({ status: filters.status === 'draft' ? null : 'draft', trashed: false })
                        }
                    >
                        Drafts
                    </Chip>
                    <Chip
                        active={filters.favorites}
                        onClick={() => visit({ favorites: !filters.favorites, trashed: false })}
                    >
                        Favorites
                    </Chip>
                    {trashedCount > 0 && (
                        <Chip
                            active={filters.trashed}
                            onClick={() => visit({ trashed: !filters.trashed, status: null })}
                        >
                            Trash ({trashedCount})
                        </Chip>
                    )}

                    <span className="mx-1 w-px shrink-0 bg-line" aria-hidden="true" />

                    <select
                        value={filters.category ?? ''}
                        onChange={(event) => visit({ category: event.target.value || null })}
                        aria-label="Filter by category"
                        className="h-11 shrink-0 rounded-full bg-surface-2 px-3 text-[0.88rem] text-ink focus:outline-none focus:ring-2 focus:ring-adriatic"
                    >
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.slug}>
                                {category.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.tag ?? ''}
                        onChange={(event) => visit({ tag: event.target.value || null })}
                        aria-label="Filter by tag"
                        className="h-11 shrink-0 rounded-full bg-surface-2 px-3 text-[0.88rem] text-ink focus:outline-none focus:ring-2 focus:ring-adriatic"
                    >
                        <option value="">All tags</option>
                        {tags.map((tag) => (
                            <option key={tag.id} value={tag.slug}>
                                {tag.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.sort}
                        onChange={(event) => visit({ sort: event.target.value })}
                        aria-label="Sort"
                        className="h-11 shrink-0 rounded-full bg-surface-2 px-3 text-[0.88rem] text-ink focus:outline-none focus:ring-2 focus:ring-adriatic"
                    >
                        <option value="updated">Recently edited</option>
                        <option value="created">Recently created</option>
                        <option value="published">Recently published</option>
                        <option value="title">Title A–Z</option>
                    </select>
                </div>
            </div>

            <p className="mt-4 text-[0.88rem] text-ink-muted" aria-live="polite">
                {recipes.meta.total} {recipes.meta.total === 1 ? 'recipe' : 'recipes'}
            </p>

            {/* --- List ----------------------------------------------------- */}
            {recipes.data.length === 0 ? (
                <div className="mt-8 rounded-2xl border border-dashed border-line-strong py-16 text-center">
                    <p className="font-display text-xl text-ink">Nothing here</p>
                    <p className="mt-2 text-[0.92rem] text-ink-muted">
                        {filters.trashed ? 'The trash is empty.' : 'No recipes match these filters.'}
                    </p>
                </div>
            ) : (
                <ul className="mt-4 space-y-2">
                    {recipes.data.map((recipe) => (
                        <li
                            key={recipe.id}
                            className="rounded-2xl bg-surface p-3 shadow-[var(--shadow-card)] ring-1 ring-line"
                        >
                            <div className="flex items-start gap-3.5">
                                {recipe.thumb ? (
                                    <img
                                        src={recipe.thumb}
                                        alt=""
                                        loading="lazy"
                                        className="size-16 shrink-0 rounded-xl object-cover"
                                    />
                                ) : (
                                    <span
                                        className="size-16 shrink-0 rounded-xl bg-surface-2"
                                        aria-hidden="true"
                                    />
                                )}

                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start gap-2">
                                        <h2 className="min-w-0 flex-1 font-medium leading-snug text-ink">
                                            {filters.trashed ? (
                                                recipe.title
                                            ) : (
                                                <Link href={recipe.editUrl} className="hover:underline">
                                                    {recipe.title}
                                                </Link>
                                            )}
                                        </h2>

                                        {recipe.status === 'draft' && !filters.trashed && (
                                            <span className="shrink-0 rounded-full bg-surface-3 px-2 py-0.5 text-[0.7rem] font-semibold uppercase tracking-wide text-ink-muted">
                                                Draft
                                            </span>
                                        )}
                                    </div>

                                    <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[0.82rem] text-ink-muted">
                                        {recipe.category && <span>{recipe.category.name}</span>}
                                        {recipe.totalMinutes && (
                                            <span>· {humaniseMinutes(recipe.totalMinutes)}</span>
                                        )}
                                        {recipe.updatedAt && (
                                            <span>
                                                · edited {new Date(recipe.updatedAt).toLocaleDateString()}
                                            </span>
                                        )}
                                    </p>

                                    <div className="mt-2.5 flex flex-wrap gap-1">
                                        {filters.trashed ? (
                                            <>
                                                <RowAction
                                                    label="Restore"
                                                    Icon={RotateCcw}
                                                    onClick={() =>
                                                        router.post(
                                                            adminRoutes.recipeRestore(recipe.id),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                />
                                                <RowAction
                                                    label="Delete permanently"
                                                    Icon={Trash2}
                                                    destructive
                                                    onClick={() =>
                                                        setConfirm({ row: recipe, permanent: true })
                                                    }
                                                />
                                            </>
                                        ) : (
                                            <>
                                                <RowAction label="Edit" Icon={Pencil} href={recipe.editUrl} />
                                                <RowAction
                                                    label={
                                                        recipe.status === 'published'
                                                            ? 'Unpublish'
                                                            : 'Publish'
                                                    }
                                                    Icon={Eye}
                                                    onClick={() =>
                                                        router.post(
                                                            adminRoutes.recipePublish(recipe.id),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                />
                                                <RowAction
                                                    label={recipe.isFavorite ? 'Unfavorite' : 'Favorite'}
                                                    Icon={Star}
                                                    active={recipe.isFavorite}
                                                    onClick={() =>
                                                        router.post(
                                                            adminRoutes.recipeFavorite(recipe.id),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                />
                                                <RowAction
                                                    label="Duplicate"
                                                    Icon={Copy}
                                                    onClick={() =>
                                                        router.post(
                                                            adminRoutes.recipeDuplicate(recipe.id),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                />
                                                <RowAction
                                                    label="Delete"
                                                    Icon={Trash2}
                                                    destructive
                                                    onClick={() =>
                                                        setConfirm({ row: recipe, permanent: false })
                                                    }
                                                />
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {recipes.meta.lastPage > 1 && (
                <nav className="mt-8 flex items-center justify-between gap-4" aria-label="Pagination">
                    {recipes.meta.prevPageUrl ? (
                        <Button variant="secondary" onClick={() => router.get(recipes.meta.prevPageUrl!)}>
                            Previous
                        </Button>
                    ) : (
                        <span />
                    )}
                    <span className="text-[0.88rem] tabular-nums text-ink-muted">
                        Page {recipes.meta.currentPage} of {recipes.meta.lastPage}
                    </span>
                    {recipes.meta.nextPageUrl ? (
                        <Button variant="secondary" onClick={() => router.get(recipes.meta.nextPageUrl!)}>
                            Next
                        </Button>
                    ) : (
                        <span />
                    )}
                </nav>
            )}

            <ConfirmDialog
                open={confirm !== null}
                title={
                    confirm?.permanent
                        ? `Permanently delete “${confirm.row.title}”?`
                        : `Delete “${confirm?.row.title}”?`
                }
                body={
                    confirm?.permanent
                        ? 'This cannot be undone. The recipe and its stored photos are removed for good.'
                        : 'It moves to the trash, where it can be restored later.'
                }
                confirmLabel={confirm?.permanent ? 'Delete for good' : 'Move to trash'}
                destructive
                onCancel={() => setConfirm(null)}
                onConfirm={() => {
                    if (!confirm) return

                    const url = confirm.permanent
                        ? adminRoutes.recipeForceDelete(confirm.row.id)
                        : adminRoutes.recipe(confirm.row.id)

                    router.delete(url, { preserveScroll: true })
                    setConfirm(null)
                }}
            />
        </AdminLayout>
    )
}

function Chip({
    active,
    onClick,
    children,
}: {
    active: boolean
    onClick: () => void
    children: React.ReactNode
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'h-11 shrink-0 rounded-full px-4 text-[0.88rem] font-medium transition',
                active ? 'bg-brand text-ink-inverse' : 'bg-surface-2 text-ink-muted hover:text-ink',
            )}
        >
            {children}
        </button>
    )
}

function RowAction({
    label,
    Icon,
    onClick,
    href,
    destructive,
    active,
}: {
    label: string
    Icon: typeof Pencil
    onClick?: () => void
    href?: string
    destructive?: boolean
    active?: boolean
}) {
    const className = cn(
        'inline-flex h-10 items-center gap-1.5 rounded-lg px-2.5 text-[0.82rem] font-medium transition',
        destructive
            ? 'text-ink-muted hover:bg-croatia-soft hover:text-croatia'
            : active
              ? 'bg-croatia-soft text-croatia'
              : 'text-ink-muted hover:bg-surface-2 hover:text-ink',
    )

    if (href) {
        return (
            <Link href={href} className={className}>
                <Icon className="size-3.5" aria-hidden="true" />
                {label}
            </Link>
        )
    }

    return (
        <button type="button" onClick={onClick} className={className}>
            <Icon className={cn('size-3.5', active && 'fill-current')} aria-hidden="true" />
            {label}
        </button>
    )
}
