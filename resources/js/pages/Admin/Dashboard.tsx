import { Link } from '@inertiajs/react'
import { FileUp, Link2, Plus, Star, Trash2 } from 'lucide-react'
import { AdminLayout } from '@/layouts/AdminLayout'
import { ButtonLink } from '@/components/ui/Button'
import { adminRoutes } from '@/lib/routes'
import { humaniseMinutes } from '@/lib/time'
import type { AdminRecipeRow } from '@/types'

interface DashboardProps {
    stats: {
        recipes: number
        published: number
        drafts: number
        favorites: number
        categories: number
        tags: number
        trashed: number
    }
    recentlyEdited: AdminRecipeRow[]
    drafts: AdminRecipeRow[]
}

export default function Dashboard({ stats, recentlyEdited, drafts }: DashboardProps) {
    return (
        <AdminLayout
            title="Dashboard"
            actions={
                <ButtonLink href={adminRoutes.recipeCreate} size="sm" aria-label="New recipe">
                    <Plus className="size-4" aria-hidden="true" />
                    <span className="hidden sm:inline">New recipe</span>
                </ButtonLink>
            }
        >
            {/* --- Primary actions ----------------------------------------- */}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <ActionCard
                    href={adminRoutes.recipeCreate}
                    title="Write a recipe"
                    body="Start from an empty editor."
                    Icon={Plus}
                />
                <ActionCard
                    href={adminRoutes.importUrl}
                    title="Import from a URL"
                    body="Paste a link and review what was found."
                    Icon={Link2}
                />
                <ActionCard
                    href={adminRoutes.importCsv}
                    title="Import a CSV"
                    body="Bring in a batch from a spreadsheet."
                    Icon={FileUp}
                />
            </div>

            {/* --- Counts --------------------------------------------------- */}
            <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Stat label="Recipes" value={stats.recipes} href={adminRoutes.recipes} />
                <Stat
                    label="Published"
                    value={stats.published}
                    href={`${adminRoutes.recipes}?status=published`}
                />
                <Stat label="Drafts" value={stats.drafts} href={`${adminRoutes.recipes}?status=draft`} />
                <Stat label="Favorites" value={stats.favorites} href={`${adminRoutes.recipes}?favorites=1`} />
                <Stat label="Categories" value={stats.categories} href={adminRoutes.categories} />
                <Stat label="Tags" value={stats.tags} href={adminRoutes.tags} />
            </div>

            {stats.trashed > 0 && (
                <Link
                    href={`${adminRoutes.recipes}?trashed=1`}
                    className="mt-4 inline-flex items-center gap-2 rounded-full bg-surface-2 px-4 py-2 text-[0.9rem] font-medium text-ink-muted transition hover:bg-surface-3 hover:text-ink"
                >
                    <Trash2 className="size-4" aria-hidden="true" />
                    {stats.trashed} in the trash
                </Link>
            )}

            <div className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-2">
                <RecipeList
                    title="Recently edited"
                    recipes={recentlyEdited}
                    emptyText="Nothing edited yet."
                />
                <RecipeList
                    title="Drafts"
                    recipes={drafts}
                    emptyText="No drafts — everything is published."
                    href={`${adminRoutes.recipes}?status=draft`}
                />
            </div>
        </AdminLayout>
    )
}

function ActionCard({
    href,
    title,
    body,
    Icon,
}: {
    href: string
    title: string
    body: string
    Icon: typeof Plus
}) {
    return (
        <Link
            href={href}
            className="group flex items-start gap-3.5 rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line transition hover:shadow-[var(--shadow-lifted)]"
        >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-adriatic-soft text-adriatic">
                <Icon className="size-5" aria-hidden="true" />
            </span>
            <span className="min-w-0">
                <span className="block font-medium text-ink">{title}</span>
                <span className="mt-0.5 block text-[0.88rem] text-ink-muted">{body}</span>
            </span>
        </Link>
    )
}

function Stat({ label, value, href }: { label: string; value: number; href: string }) {
    return (
        <Link
            href={href}
            className="rounded-2xl bg-surface p-4 ring-1 ring-line transition hover:ring-line-strong"
        >
            <span className="block font-display text-[2rem] leading-none tabular-nums text-ink">{value}</span>
            <span className="mt-1.5 block text-[0.78rem] font-medium uppercase tracking-[0.1em] text-ink-faint">
                {label}
            </span>
        </Link>
    )
}

function RecipeList({
    title,
    recipes,
    emptyText,
    href,
}: {
    title: string
    recipes: AdminRecipeRow[]
    emptyText: string
    href?: string
}) {
    return (
        <section className="min-w-0">
            <div className="flex items-baseline justify-between gap-4">
                <h2 className="font-display text-[1.4rem] tracking-tight text-ink">{title}</h2>
                {href && recipes.length > 0 && (
                    <Link
                        href={href}
                        className="-my-2 inline-flex min-h-11 items-center text-[0.88rem] font-medium text-adriatic"
                    >
                        View all
                    </Link>
                )}
            </div>

            {recipes.length === 0 ? (
                <p className="mt-4 rounded-2xl border border-dashed border-line-strong px-4 py-8 text-center text-[0.92rem] text-ink-muted">
                    {emptyText}
                </p>
            ) : (
                <ul className="mt-4 divide-y divide-line overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
                    {recipes.map((recipe) => (
                        <li key={recipe.id}>
                            <Link
                                href={recipe.editUrl}
                                className="flex items-center gap-3.5 p-3 transition hover:bg-surface-2"
                            >
                                {recipe.thumb ? (
                                    <img
                                        src={recipe.thumb}
                                        alt=""
                                        loading="lazy"
                                        className="size-12 shrink-0 rounded-xl object-cover"
                                    />
                                ) : (
                                    <span
                                        className="size-12 shrink-0 rounded-xl bg-surface-2"
                                        aria-hidden="true"
                                    />
                                )}

                                <span className="min-w-0 flex-1">
                                    <span className="flex items-center gap-1.5">
                                        <span className="min-w-0 truncate font-medium text-ink">
                                            {recipe.title}
                                        </span>
                                        {recipe.isFavorite && (
                                            <Star
                                                className="size-3.5 shrink-0 fill-croatia text-croatia"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </span>
                                    <span className="mt-0.5 flex items-center gap-2 text-[0.82rem] text-ink-muted">
                                        {recipe.status === 'draft' && (
                                            <span className="rounded-full bg-surface-3 px-2 py-0.5 text-[0.72rem] font-medium uppercase tracking-wide">
                                                Draft
                                            </span>
                                        )}
                                        {recipe.category?.name}
                                        {recipe.totalMinutes
                                            ? ` · ${humaniseMinutes(recipe.totalMinutes)}`
                                            : ''}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    )
}
