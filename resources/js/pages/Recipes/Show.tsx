import { Link } from '@inertiajs/react'
import { ChefHat, Clock, ExternalLink, Flame, Star, Timer, Users } from 'lucide-react'
import { useMemo, useState } from 'react'
import { CheckerRule } from '@/components/CheckerRule'
import { IngredientList } from '@/components/IngredientList'
import { Lightbox } from '@/components/Lightbox'
import { RecipeCard } from '@/components/RecipeCard'
import { ResponsiveImage } from '@/components/ResponsiveImage'
import { ServingScaler } from '@/components/ServingScaler'
import { CookingMode } from '@/components/cooking/CookingMode'
import { useLocalState } from '@/hooks/useLocalState'
import { useTimers } from '@/hooks/useTimers'
import { cn } from '@/lib/cn'
import { detectDurations, timerLabel } from '@/lib/time'
import { PublicLayout } from '@/layouts/PublicLayout'
import type { RecipeCardData, RecipeDetail } from '@/types'

interface ShowProps {
    recipe: RecipeDetail
    related: RecipeCardData[]
}

export default function RecipeShow({ recipe, related }: ShowProps) {
    const baseServings = recipe.servings ?? 0

    // Scaling, gathered ingredients and finished steps all live in the
    // browser — the site has no visitor accounts and stores nothing per person
    // on the server.
    const [servings, setServings] = useLocalState(`ribs:servings:${recipe.slug}`, baseServings || 1)
    const [checked, setChecked] = useLocalState<number[]>(`ribs:checked:${recipe.slug}`, [])
    const [completed, setCompleted] = useLocalState<number[]>(`ribs:steps:${recipe.slug}`, [])

    const [cooking, setCooking] = useState(false)
    const [lightbox, setLightbox] = useState<number | null>(null)

    const { start: startTimer } = useTimers()

    const factor = baseServings > 0 ? servings / baseServings : 1

    const gallery = useMemo(
        () => (recipe.heroImage ? [recipe.heroImage, ...recipe.gallery] : recipe.gallery),
        [recipe.heroImage, recipe.gallery],
    )

    const toggle = (list: number[], setList: (value: number[]) => void, id: number) =>
        setList(list.includes(id) ? list.filter((item) => item !== id) : [...list, id])

    const metrics = [
        recipe.prepTime && { icon: ChefHat, label: 'Prep', value: recipe.prepTime },
        recipe.cookTime && { icon: Flame, label: 'Cook', value: recipe.cookTime },
        recipe.totalTime && { icon: Clock, label: 'Total', value: recipe.totalTime },
        baseServings > 0 && { icon: Users, label: 'Serves', value: String(servings) },
        recipe.calories && { icon: Flame, label: 'Per serving', value: `${recipe.calories} cal` },
    ].filter(Boolean) as { icon: typeof Clock; label: string; value: string }[]

    return (
        <PublicLayout>
            <article className="pb-20">
                {/* -----------------------------------------------------------
                    Hero. Edge to edge on a phone, inset on wider screens.
                ----------------------------------------------------------- */}
                {recipe.heroImage && (
                    <div className="shell gutter pt-4 sm:pt-8">
                        <button
                            type="button"
                            onClick={() => setLightbox(0)}
                            className="block w-full overflow-hidden rounded-[1.5rem] shadow-[var(--shadow-lifted)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-adriatic sm:rounded-[1.75rem]"
                            aria-label="View photo full screen"
                        >
                            {/* Taller on a phone, where a wide crop would
                                reduce the hero to a letterbox strip. */}
                            <ResponsiveImage
                                image={recipe.heroImage}
                                alt={recipe.heroImage.alt ?? recipe.title}
                                sizes="(min-width: 1024px) 72rem, 100vw"
                                priority
                                rounded={false}
                                aspect={null}
                                className="aspect-[4/3] sm:aspect-[16/9]"
                            />
                        </button>
                    </div>
                )}

                <header className="shell gutter pt-7 sm:pt-10">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        {recipe.category && (
                            <Link
                                href={recipe.category.url}
                                className="-my-2 inline-flex items-center py-2 text-[0.72rem] font-semibold uppercase tracking-[0.15em] text-adriatic transition hover:underline"
                            >
                                {recipe.category.name}
                            </Link>
                        )}
                        {recipe.isFavorite && (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-croatia-soft px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.1em] text-croatia">
                                <Star className="size-3 fill-current" aria-hidden="true" />
                                Favorite
                            </span>
                        )}
                    </div>

                    <h1 className="mt-3 max-w-3xl font-display text-[clamp(2.4rem,9vw,4rem)] leading-[0.99] tracking-[-0.025em] text-ink text-balance">
                        {recipe.title}
                    </h1>

                    {recipe.description && (
                        <p className="mt-4 max-w-2xl text-[1.08rem] leading-relaxed text-ink-muted text-balance">
                            {recipe.description}
                        </p>
                    )}

                    {metrics.length > 0 && (
                        <dl className="mt-7 flex flex-wrap gap-x-8 gap-y-4 border-y border-line py-5">
                            {metrics.map((metric) => (
                                <div key={metric.label} className="flex items-center gap-2.5">
                                    <metric.icon
                                        className="size-4 shrink-0 text-ink-faint"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <dt className="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-faint">
                                            {metric.label}
                                        </dt>
                                        <dd className="font-medium tabular-nums text-ink">{metric.value}</dd>
                                    </div>
                                </div>
                            ))}
                        </dl>
                    )}
                </header>

                {/* -----------------------------------------------------------
                    Start Cooking. Sticky on mobile so it is always reachable.
                ----------------------------------------------------------- */}
                <div className="shell gutter sticky bottom-0 z-20 mt-6 bg-gradient-to-t from-canvas via-canvas to-transparent pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-4 lg:static lg:bg-none lg:pb-0">
                    <button
                        type="button"
                        onClick={() => setCooking(true)}
                        disabled={recipe.steps.length === 0}
                        className="flex h-14 w-full items-center justify-center gap-2.5 rounded-2xl bg-brand text-[1.05rem] font-semibold text-ink-inverse shadow-[var(--shadow-lifted)] transition duration-150 active:scale-[0.99] disabled:opacity-40 lg:w-auto lg:px-10"
                    >
                        <ChefHat className="size-5" aria-hidden="true" />
                        Start cooking
                    </button>
                </div>

                {/* -----------------------------------------------------------
                    Ingredients and instructions.
                ----------------------------------------------------------- */}
                <div className="shell gutter mt-10 grid grid-cols-1 gap-12 lg:mt-14 lg:grid-cols-[22rem_minmax(0,1fr)] lg:gap-16">
                    <section
                        aria-labelledby="ingredients-heading"
                        className="lg:sticky lg:top-24 lg:self-start"
                    >
                        <h2
                            id="ingredients-heading"
                            className="font-display text-[1.8rem] leading-tight tracking-[-0.015em] text-ink"
                        >
                            Ingredients
                        </h2>

                        {baseServings > 0 && (
                            <ServingScaler
                                baseServings={baseServings}
                                servings={servings}
                                label={recipe.servingsLabel}
                                onChange={setServings}
                                className="mt-5 rounded-2xl bg-surface-2 p-4"
                            />
                        )}

                        {recipe.ingredients.length > 0 ? (
                            <IngredientList
                                ingredients={recipe.ingredients}
                                factor={factor}
                                checked={checked}
                                onToggle={(id) => toggle(checked, setChecked, id)}
                                className="mt-5"
                            />
                        ) : (
                            <p className="mt-5 text-ink-muted">No ingredients listed.</p>
                        )}

                        {checked.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setChecked([])}
                                className="mt-4 text-[0.88rem] font-medium text-adriatic underline underline-offset-4"
                            >
                                Reset checklist
                            </button>
                        )}
                    </section>

                    <section aria-labelledby="method-heading">
                        <h2
                            id="method-heading"
                            className="font-display text-[1.8rem] leading-tight tracking-[-0.015em] text-ink"
                        >
                            Method
                        </h2>

                        <ol className="mt-6 space-y-8">
                            {recipe.steps.map((step, index) => {
                                const seconds =
                                    step.timerSeconds ?? detectDurations(step.instruction)[0]?.seconds ?? null
                                const isDone = completed.includes(step.id)

                                return (
                                    <li
                                        key={step.id}
                                        id={`step-${index + 1}`}
                                        className="flex gap-4 sm:gap-5"
                                    >
                                        <button
                                            type="button"
                                            onClick={() => toggle(completed, setCompleted, step.id)}
                                            aria-pressed={isDone}
                                            aria-label={`Mark step ${index + 1} ${isDone ? 'not done' : 'done'}`}
                                            className={cn(
                                                'mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-full font-display text-[1.05rem] tabular-nums transition',
                                                isDone
                                                    ? 'bg-olive text-white'
                                                    : 'bg-surface-2 text-ink-muted hover:bg-surface-3',
                                            )}
                                        >
                                            {index + 1}
                                        </button>

                                        <div
                                            className={cn(
                                                'min-w-0 flex-1 transition-opacity',
                                                isDone && 'opacity-50',
                                            )}
                                        >
                                            <p className="text-[1.05rem] leading-relaxed text-ink">
                                                {step.instruction}
                                            </p>

                                            {step.image && (
                                                <img
                                                    src={step.image.src}
                                                    srcSet={step.image.srcset ?? undefined}
                                                    sizes="(min-width: 1024px) 36rem, 92vw"
                                                    alt={step.image.alt ?? ''}
                                                    loading="lazy"
                                                    className="mt-3 w-full rounded-xl object-cover"
                                                />
                                            )}

                                            {seconds && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        startTimer(
                                                            seconds,
                                                            `${recipe.title} — step ${index + 1}`,
                                                        )
                                                    }
                                                    className="mt-3 inline-flex h-10 items-center gap-2 rounded-full bg-adriatic-soft px-4 text-[0.88rem] font-semibold text-adriatic transition active:scale-[0.98]"
                                                >
                                                    <Timer className="size-4" aria-hidden="true" />
                                                    Start {timerLabel(seconds)} timer
                                                </button>
                                            )}
                                        </div>
                                    </li>
                                )
                            })}
                        </ol>

                        {recipe.notes && (
                            <div className="mt-12 rounded-2xl bg-surface-2 p-6">
                                <h3 className="font-display text-[1.35rem] tracking-tight text-ink">Notes</h3>
                                <div className="mt-3 space-y-3 text-[1rem] leading-relaxed text-ink-muted">
                                    {recipe.notes.split(/\n{2,}/).map((paragraph, index) => (
                                        <p key={index}>{paragraph}</p>
                                    ))}
                                </div>
                            </div>
                        )}

                        {recipe.gallery.length > 0 && (
                            <div className="mt-12">
                                <h3 className="font-display text-[1.35rem] tracking-tight text-ink">
                                    Gallery
                                </h3>
                                <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {recipe.gallery.map((image, index) => (
                                        <button
                                            key={image.id}
                                            type="button"
                                            onClick={() => setLightbox(recipe.heroImage ? index + 1 : index)}
                                            className="overflow-hidden rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-adriatic"
                                            aria-label={image.caption ?? `Photo ${index + 1}`}
                                        >
                                            <ResponsiveImage
                                                image={image}
                                                sizes="(min-width: 640px) 14rem, 45vw"
                                                aspect="1 / 1"
                                                rounded={false}
                                                imgClassName="transition-transform duration-300 hover:scale-105"
                                            />
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {(recipe.sourceUrl || recipe.sourceName || recipe.tags.length > 0) && (
                            <footer className="mt-12 border-t border-line pt-6">
                                {recipe.tags.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {recipe.tags.map((tag) => (
                                            <Link
                                                key={tag.id}
                                                href={tag.url}
                                                className="inline-flex min-h-10 items-center rounded-full bg-surface-2 px-4 text-[0.85rem] font-medium text-ink-muted transition hover:bg-surface-3 hover:text-ink"
                                            >
                                                {tag.name}
                                            </Link>
                                        ))}
                                    </div>
                                )}

                                {(recipe.sourceUrl || recipe.sourceName) && (
                                    <p className="mt-5 text-[0.9rem] text-ink-faint">
                                        {recipe.sourceUrl ? (
                                            <>
                                                Adapted from{' '}
                                                <a
                                                    href={recipe.sourceUrl}
                                                    target="_blank"
                                                    rel="noreferrer noopener nofollow"
                                                    className="inline-flex items-center gap-1 font-medium text-adriatic underline underline-offset-4"
                                                >
                                                    {recipe.sourceName ?? 'the original recipe'}
                                                    <ExternalLink className="size-3" aria-hidden="true" />
                                                </a>
                                            </>
                                        ) : (
                                            <>Source: {recipe.sourceName}</>
                                        )}
                                    </p>
                                )}
                            </footer>
                        )}
                    </section>
                </div>

                {related.length > 0 && (
                    <section className="shell gutter mt-20" aria-labelledby="related-heading">
                        <CheckerRule className="mb-10" />
                        <h2
                            id="related-heading"
                            className="font-display text-[1.75rem] leading-tight tracking-[-0.015em] text-ink"
                        >
                            Cook this next
                        </h2>
                        <div className="mt-6 grid grid-cols-1 gap-x-5 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
                            {related.slice(0, 3).map((item) => (
                                <RecipeCard key={item.id} recipe={item} />
                            ))}
                        </div>
                    </section>
                )}
            </article>

            <Lightbox
                images={gallery}
                index={lightbox}
                onClose={() => setLightbox(null)}
                onNavigate={setLightbox}
            />

            {cooking && (
                <CookingMode
                    title={recipe.title}
                    steps={recipe.steps}
                    ingredients={recipe.ingredients}
                    servings={servings}
                    baseServings={baseServings}
                    servingsLabel={recipe.servingsLabel}
                    onServingsChange={setServings}
                    checkedIngredients={checked}
                    onToggleIngredient={(id) => toggle(checked, setChecked, id)}
                    completedSteps={completed}
                    onToggleStep={(id) => toggle(completed, setCompleted, id)}
                    onExit={() => setCooking(false)}
                />
            )}
        </PublicLayout>
    )
}
