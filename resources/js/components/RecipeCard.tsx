import { Link } from '@inertiajs/react'
import { Clock, Star, Users } from 'lucide-react'
import { cn } from '@/lib/cn'
import { ResponsiveImage } from './ResponsiveImage'
import type { RecipeCardData } from '@/types'

interface RecipeCardProps {
    recipe: RecipeCardData
    /** `rail` is the fixed-width card used inside horizontal sections. */
    layout?: 'grid' | 'rail'
    priority?: boolean
    className?: string
}

export function RecipeCard({ recipe, layout = 'grid', priority = false, className }: RecipeCardProps) {
    return (
        <article
            className={cn('group relative', layout === 'rail' && 'w-[76vw] shrink-0 sm:w-64', className)}
        >
            <Link
                href={recipe.url}
                prefetch="hover"
                className="block rounded-card focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-adriatic"
            >
                <div className="relative overflow-hidden rounded-card bg-surface shadow-[var(--shadow-card)] ring-1 ring-line transition duration-300 ease-[var(--ease-out-soft)] group-hover:shadow-[var(--shadow-lifted)]">
                    <ResponsiveImage
                        image={recipe.image}
                        alt={recipe.image?.alt ?? recipe.title}
                        sizes={
                            layout === 'rail'
                                ? '(min-width: 640px) 16rem, 76vw'
                                : '(min-width: 1024px) 22rem, (min-width: 640px) 45vw, 92vw'
                        }
                        priority={priority}
                        aspect="4 / 3"
                        imgClassName="transition-transform duration-500 ease-[var(--ease-out-soft)] group-hover:scale-[1.035]"
                        rounded={false}
                    />

                    {recipe.isFavorite && (
                        <span
                            className="absolute left-3 top-3 flex size-7 items-center justify-center rounded-full bg-canvas/85 backdrop-blur-sm"
                            title="A favorite"
                        >
                            <Star className="size-3.5 fill-croatia text-croatia" aria-hidden="true" />
                            <span className="sr-only">Featured recipe</span>
                        </span>
                    )}
                </div>
            </Link>

            <div className="px-1 pt-3">
                {recipe.category && (
                    <Link
                        href={recipe.category.url}
                        className="text-[0.68rem] font-semibold uppercase tracking-[0.13em] text-ink-faint transition-colors hover:text-adriatic"
                    >
                        {recipe.category.name}
                    </Link>
                )}

                <h3 className="mt-1 font-display text-[1.32rem] leading-[1.15] tracking-[-0.01em] text-ink">
                    <Link
                        href={recipe.url}
                        prefetch="hover"
                        className="after:absolute after:inset-0 after:content-['']"
                    >
                        {recipe.title}
                    </Link>
                </h3>

                <p className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.82rem] text-ink-muted">
                    {recipe.totalTime && (
                        <span className="inline-flex items-center gap-1">
                            <Clock className="size-3.5" aria-hidden="true" />
                            {recipe.totalTime}
                        </span>
                    )}
                    {recipe.servings && (
                        <span className="inline-flex items-center gap-1">
                            <Users className="size-3.5" aria-hidden="true" />
                            Serves {recipe.servings}
                        </span>
                    )}
                </p>
            </div>
        </article>
    )
}

export function RecipeCardSkeleton({ layout = 'grid' }: { layout?: 'grid' | 'rail' }) {
    return (
        <div className={cn(layout === 'rail' && 'w-[76vw] shrink-0 sm:w-64')} aria-hidden="true">
            <div className="skeleton aspect-[4/3] rounded-card" />
            <div className="space-y-2 px-1 pt-3">
                <div className="skeleton h-2.5 w-16 rounded-full" />
                <div className="skeleton h-5 w-4/5 rounded-md" />
                <div className="skeleton h-3 w-2/5 rounded-full" />
            </div>
        </div>
    )
}
