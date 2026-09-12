import { Link } from '@inertiajs/react'
import { ArrowRight, Clock, Search, Users } from 'lucide-react'
import { useState } from 'react'
import { CheckerRule } from '@/components/CheckerRule'
import { RecipeRail } from '@/components/RecipeRail'
import { ResponsiveImage } from '@/components/ResponsiveImage'
import { SearchOverlay } from '@/components/SearchOverlay'
import { PublicLayout } from '@/layouts/PublicLayout'
import type { RecipeCardData } from '@/types'

interface HomeSection {
    key: string
    title: string
    subtitle: string | null
    href: string
    recipes: RecipeCardData[]
}

interface HomeProps {
    spotlight: RecipeCardData | null
    sections: HomeSection[]
    categories: { id: number; name: string; slug: string; icon: string | null; count: number; url: string }[]
    totalRecipes: number
}

export default function Home({ spotlight, sections, categories, totalRecipes }: HomeProps) {
    const [searchOpen, setSearchOpen] = useState(false)

    return (
        <PublicLayout showCategoryRail={false}>
            {/* ---------------------------------------------------------------
                Hero: one sentence, one search field, one photograph.
            --------------------------------------------------------------- */}
            <section className="shell gutter pt-8 sm:pt-14">
                <div className="grid grid-cols-1 items-center gap-8 lg:grid-cols-[1.05fr_minmax(0,1fr)] lg:gap-14">
                    <div>
                        <p className="flex items-center gap-2.5 text-[0.7rem] font-semibold uppercase tracking-[0.18em] text-ink-faint">
                            <span
                                className="checker h-2 w-6 rounded-[1px]"
                                style={{ ['--checker-size' as string]: '3px' }}
                                aria-hidden="true"
                            />
                            {totalRecipes} {totalRecipes === 1 ? 'recipe' : 'recipes'} and counting
                        </p>

                        <h1 className="mt-4 font-display text-[clamp(2.75rem,9vw,4.25rem)] leading-[0.98] tracking-[-0.025em] text-ink text-balance">
                            What are we cooking?
                        </h1>

                        <p className="mt-4 max-w-md text-[1.05rem] leading-relaxed text-ink-muted text-balance">
                            A family collection of things worth making again — Adriatic plates, weeknight
                            dinners, and breakfasts that actually keep.
                        </p>

                        <button
                            type="button"
                            onClick={() => setSearchOpen(true)}
                            className="group mt-7 flex h-14 w-full max-w-md items-center gap-3 rounded-2xl bg-surface px-5 text-left shadow-[var(--shadow-card)] ring-1 ring-line-strong transition duration-200 ease-[var(--ease-out-soft)] hover:shadow-[var(--shadow-lifted)] active:scale-[0.995]"
                        >
                            <Search className="size-5 shrink-0 text-ink-faint" aria-hidden="true" />
                            <span className="flex-1 text-[1.02rem] text-ink-faint">
                                Search recipes and ingredients
                            </span>
                            <kbd className="hidden rounded-md bg-surface-2 px-1.5 py-0.5 font-sans text-xs text-ink-faint sm:block">
                                /
                            </kbd>
                        </button>
                    </div>

                    {spotlight && (
                        <Link
                            href={spotlight.url}
                            className="group relative block overflow-hidden rounded-[1.75rem] shadow-[var(--shadow-lifted)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-adriatic"
                        >
                            <ResponsiveImage
                                image={spotlight.image}
                                alt={spotlight.image?.alt ?? spotlight.title}
                                sizes="(min-width: 1024px) 36rem, 92vw"
                                priority
                                aspect="5 / 4"
                                rounded={false}
                                imgClassName="transition-transform duration-700 ease-[var(--ease-out-soft)] group-hover:scale-[1.03]"
                            />

                            {/* Gradient rather than a flat scrim, so the photo
                                keeps its depth behind the type. */}
                            <div
                                className="absolute inset-0 bg-gradient-to-t from-black/72 via-black/18 to-transparent"
                                aria-hidden="true"
                            />

                            <div className="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                                {spotlight.category && (
                                    <span className="text-[0.68rem] font-semibold uppercase tracking-[0.15em] text-white/75">
                                        {spotlight.category.name}
                                    </span>
                                )}
                                <h2 className="mt-1.5 font-display text-[1.9rem] leading-[1.08] tracking-[-0.015em] text-white sm:text-[2.3rem]">
                                    {spotlight.title}
                                </h2>
                                <p className="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.85rem] text-white/80">
                                    {spotlight.totalTime && (
                                        <span className="inline-flex items-center gap-1.5">
                                            <Clock className="size-3.5" aria-hidden="true" />
                                            {spotlight.totalTime}
                                        </span>
                                    )}
                                    {spotlight.servings && (
                                        <span className="inline-flex items-center gap-1.5">
                                            <Users className="size-3.5" aria-hidden="true" />
                                            Serves {spotlight.servings}
                                        </span>
                                    )}
                                </p>
                            </div>
                        </Link>
                    )}
                </div>
            </section>

            {/* Category chips — the fastest way into the collection on a phone. */}
            {categories.length > 0 && (
                <nav aria-label="Browse by category" className="mt-10 sm:mt-14">
                    <div className="rail flex gap-2.5 overflow-x-auto px-[clamp(1rem,4vw,2.5rem)] pb-1">
                        {categories.map((category) => (
                            <Link
                                key={category.id}
                                href={category.url}
                                className="group flex shrink-0 items-center gap-2 rounded-full bg-surface px-4 py-2.5 text-[0.92rem] font-medium text-ink shadow-[var(--shadow-card)] ring-1 ring-line transition duration-150 hover:ring-line-strong active:scale-[0.98]"
                            >
                                {category.name}
                                <span className="text-[0.78rem] text-ink-faint">{category.count}</span>
                            </Link>
                        ))}
                    </div>
                </nav>
            )}

            {sections.map((section, index) => (
                <RecipeRail
                    key={section.key}
                    title={section.title}
                    subtitle={section.subtitle}
                    href={section.href}
                    recipes={section.recipes}
                    priority={index === 0}
                />
            ))}

            {sections.length === 0 && (
                <div className="shell gutter py-20 text-center">
                    <CheckerRule className="mx-auto mb-8 max-w-xs" />
                    <h2 className="font-display text-3xl tracking-tight text-ink">Nothing here yet</h2>
                    <p className="mx-auto mt-3 max-w-sm text-ink-muted">
                        The collection is empty. Published recipes will appear here.
                    </p>
                </div>
            )}

            {sections.length > 0 && (
                <div className="shell gutter pb-4 pt-6">
                    <Link
                        href="/recipes"
                        className="group flex items-center justify-between gap-4 rounded-2xl bg-surface-2 px-6 py-6 transition hover:bg-surface-3 sm:px-8"
                    >
                        <span>
                            <span className="block font-display text-[1.5rem] leading-tight tracking-tight text-ink">
                                Browse everything
                            </span>
                            <span className="mt-1 block text-[0.92rem] text-ink-muted">
                                Filter by category, tag, or how long you have.
                            </span>
                        </span>
                        <ArrowRight
                            className="size-5 shrink-0 text-ink-muted transition-transform duration-200 group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            )}

            <SearchOverlay open={searchOpen} onClose={() => setSearchOpen(false)} />
        </PublicLayout>
    )
}
