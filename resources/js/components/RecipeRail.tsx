import { Link } from '@inertiajs/react'
import { ArrowRight } from 'lucide-react'
import { RecipeCard } from './RecipeCard'
import type { RecipeCardData } from '@/types'

interface RecipeRailProps {
    title: string
    subtitle?: string | null
    href: string
    recipes: RecipeCardData[]
    priority?: boolean
}

/**
 * A horizontally scrolling section.
 *
 * Native overflow scrolling with snap points rather than a carousel library:
 * it is one line of CSS, it is perfectly smooth on a phone, and it keeps
 * keyboard and screen-reader navigation working because every card is just a
 * link in the document.
 */
export function RecipeRail({ title, subtitle, href, recipes, priority = false }: RecipeRailProps) {
    if (recipes.length === 0) return null

    return (
        <section className="py-8 sm:py-10">
            <div className="shell gutter flex items-end justify-between gap-4">
                <div className="min-w-0">
                    <h2 className="font-display text-[1.75rem] leading-tight tracking-[-0.015em] text-ink sm:text-[2rem]">
                        {title}
                    </h2>
                    {subtitle && <p className="mt-1 text-[0.95rem] text-ink-muted">{subtitle}</p>}
                </div>

                <Link
                    href={href}
                    className="group inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full text-[0.92rem] font-medium text-adriatic transition hover:gap-2.5"
                >
                    View all
                    <ArrowRight className="size-4 transition-transform" aria-hidden="true" />
                </Link>
            </div>

            <div className="rail rail-inset mt-5 flex gap-4 overflow-x-auto pb-2 sm:gap-5">
                {recipes.map((recipe, index) => (
                    <RecipeCard
                        key={recipe.id}
                        recipe={recipe}
                        layout="rail"
                        priority={priority && index === 0}
                    />
                ))}
            </div>
        </section>
    )
}
