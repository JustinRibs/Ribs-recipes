import { useForm } from '@inertiajs/react'
import { AlertTriangle, ArrowRight, Check, Download, Link2, Loader2, Save, Sparkles } from 'lucide-react'
import { useState } from 'react'
import { RecipeForm } from '@/components/admin/RecipeForm'
import { Button } from '@/components/ui/Button'
import { TextField } from '@/components/ui/Field'
import { AdminLayout } from '@/layouts/AdminLayout'
import { adminRoutes } from '@/lib/routes'
import { cn } from '@/lib/cn'
import type { CategoryOption, ImageResource, RecipeFormState } from '@/types'

interface Draft {
    form: RecipeFormState
    heroImageUrl: string | null
    warnings: string[]
    extractedVia: 'json-ld' | 'microdata' | 'fallback' | string
    sourceUrl: string | null
}

interface UrlImportProps {
    draft: Draft | null
    submittedUrl?: string
    categories: CategoryOption[]
    allTags: string[]
}

const SOURCE_LABELS: Record<string, string> = {
    'json-ld': 'Read from the page’s structured recipe data (JSON-LD) — the most reliable source.',
    microdata: 'Read from the page’s embedded microdata.',
    fallback: 'No structured recipe data was published, so only basic page details could be read.',
}

export default function UrlImport({ draft, submittedUrl, categories, allTags }: UrlImportProps) {
    const fetchForm = useForm({ url: submittedUrl ?? '' })

    const submitUrl = (event: React.FormEvent) => {
        event.preventDefault()
        fetchForm.post(adminRoutes.importUrl, { preserveScroll: false })
    }

    return (
        <AdminLayout
            title="Import from a URL"
            description="Paste a recipe link. Nothing is saved until you review what was found and press Save."
        >
            <form
                onSubmit={submitUrl}
                className="rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-6"
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <TextField
                        label="Recipe address"
                        type="url"
                        inputMode="url"
                        required
                        value={fetchForm.data.url}
                        onChange={(event) => fetchForm.setData('url', event.target.value)}
                        error={fetchForm.errors.url}
                        placeholder="https://example.com/recipes/banana-baked-oats"
                        wrapperClassName="flex-1"
                        autoComplete="off"
                    />

                    <Button type="submit" disabled={fetchForm.processing} className="sm:mb-0.5">
                        {fetchForm.processing ? (
                            <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                        ) : (
                            <Sparkles className="size-4" aria-hidden="true" />
                        )}
                        {fetchForm.processing ? 'Reading…' : 'Read recipe'}
                    </Button>
                </div>

                <p className="mt-3 text-[0.85rem] text-ink-muted">
                    Only public http(s) addresses are fetched. Private, loopback and internal network
                    addresses are refused, redirects are re-checked at every hop, and the download is size-
                    and time-limited.
                </p>
            </form>

            {/* Keyed on the source, so importing a second URL into the same
                mounted page starts from a clean form rather than an effect
                copying the new draft over the old state field by field. */}
            {draft && (
                <ImportPreview
                    key={draft.sourceUrl ?? 'draft'}
                    draft={draft}
                    categories={categories}
                    allTags={allTags}
                />
            )}
        </AdminLayout>
    )
}

function ImportPreview({
    draft,
    categories,
    allTags,
}: {
    draft: Draft
    categories: CategoryOption[]
    allTags: string[]
}) {
    const { data, setData, post, processing, errors } = useForm<RecipeFormState>(draft.form)
    const [heroState, setHeroState] = useState<'idle' | 'working' | 'added' | 'failed'>('idle')
    const [heroError, setHeroError] = useState<string | null>(null)

    const attachHero = async (mode: 'download' | 'link') => {
        if (!draft.heroImageUrl) return

        setHeroState('working')
        setHeroError(null)

        try {
            const response = await fetch(adminRoutes.mediaRemote, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ url: draft.heroImageUrl, mode, alt: data.title }),
            })

            const payload = (await response.json()) as {
                image?: ImageResource
                errors?: Record<string, string[]>
                message?: string
            }

            if (!response.ok || !payload.image) {
                throw new Error(
                    payload.errors?.url?.[0] ?? payload.message ?? 'That photo could not be added.',
                )
            }

            setData('images', [
                ...data.images,
                {
                    ...payload.image,
                    caption: '',
                    alt: payload.image.alt ?? data.title,
                    isHero: data.images.length === 0,
                },
            ])
            setHeroState('added')
        } catch (error) {
            setHeroError((error as Error).message)
            setHeroState('failed')
        }
    }

    const save = (event: React.FormEvent) => {
        event.preventDefault()
        post(adminRoutes.recipes, { preserveScroll: true })
    }

    return (
        <form onSubmit={save} className="mt-8">
            <div className="mb-5 rounded-2xl bg-adriatic-soft p-4 sm:p-5">
                <h2 className="flex items-center gap-2 font-display text-[1.3rem] tracking-tight text-ink">
                    <Check className="size-4.5 text-adriatic" aria-hidden="true" />
                    Review before saving
                </h2>

                <p className="mt-2 text-[0.92rem] leading-relaxed text-ink-muted">
                    {SOURCE_LABELS[draft.extractedVia] ?? 'Recipe details were extracted from the page.'}{' '}
                    Nothing has been saved yet — correct anything below, then press Save recipe.
                </p>

                {draft.warnings.length > 0 && (
                    <ul className="mt-3 space-y-1.5">
                        {draft.warnings.map((warning, index) => (
                            <li key={index} className="flex items-start gap-2 text-[0.88rem] text-ink">
                                <AlertTriangle
                                    className="mt-0.5 size-3.5 shrink-0 text-croatia"
                                    aria-hidden="true"
                                />
                                {warning}
                            </li>
                        ))}
                    </ul>
                )}

                <p className="mt-3 flex flex-wrap items-center gap-1.5 text-[0.85rem] text-ink-muted">
                    <Link2 className="size-3.5" aria-hidden="true" />
                    <span className="truncate">{draft.sourceUrl}</span>
                </p>
            </div>

            {draft.heroImageUrl && (
                <div className="mb-5 rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-5">
                    <h3 className="font-display text-[1.2rem] tracking-tight text-ink">
                        Photo from the source
                    </h3>

                    <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start">
                        <img
                            src={draft.heroImageUrl}
                            alt=""
                            className="h-32 w-full shrink-0 rounded-xl object-cover sm:w-48"
                            loading="lazy"
                        />

                        <div className="min-w-0 flex-1">
                            {heroState === 'added' ? (
                                <p className="flex items-center gap-2 text-[0.92rem] font-medium text-olive">
                                    <Check className="size-4" aria-hidden="true" />
                                    Added to this recipe.
                                </p>
                            ) : (
                                <>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            disabled={heroState === 'working'}
                                            onClick={() => void attachHero('download')}
                                        >
                                            <Download className="size-4" aria-hidden="true" />
                                            Download and store
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={heroState === 'working'}
                                            onClick={() => void attachHero('link')}
                                        >
                                            <Link2 className="size-4" aria-hidden="true" />
                                            Keep the original link
                                        </Button>
                                    </div>

                                    <p className="mt-2.5 text-[0.85rem] text-ink-muted">
                                        Storing a copy means the photo survives the source site changing.
                                        Keeping the link saves disk space but depends on that site.
                                    </p>
                                </>
                            )}

                            {heroError && (
                                <p className="mt-2 text-[0.88rem] text-croatia" role="alert">
                                    {heroError}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <RecipeForm
                data={data}
                setData={setData}
                errors={errors as Partial<Record<string, string>>}
                categories={categories}
                allTags={allTags}
            />

            <div
                className={cn(
                    'safe-dock sticky bottom-0 z-20 -mx-4 mt-6 border-t border-line bg-canvas/92 px-4 pt-3 backdrop-blur-xl sm:-mx-6 sm:px-6',
                )}
            >
                <Button type="submit" size="lg" disabled={processing} className="w-full sm:w-auto">
                    <Save className="size-4.5" aria-hidden="true" />
                    {processing ? 'Saving…' : 'Save recipe'}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Button>
            </div>
        </form>
    )
}
