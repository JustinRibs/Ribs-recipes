import { Link, router, useForm } from '@inertiajs/react'
import { Copy, Eye, RotateCcw, Save, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { RecipeForm } from '@/components/admin/RecipeForm'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { AdminLayout } from '@/layouts/AdminLayout'
import { readLocal, removeLocal, writeLocal } from '@/hooks/useLocalState'
import { adminRoutes } from '@/lib/routes'
import type { CategoryOption, RecipeFormState } from '@/types'

interface FormPageProps {
    recipe: (RecipeFormState & { id: number; slug: string }) | null
    categories: CategoryOption[]
    allTags: string[]
}

const DRAFT_KEY = 'ribs:recipe-draft:new'

export const emptyRecipe = (): RecipeFormState => ({
    title: '',
    slug: '',
    description: '',
    notes: '',
    category_id: null,
    tags: [],
    prep_minutes: null,
    cook_minutes: null,
    total_minutes_override: null,
    servings: 4,
    servings_label: '',
    calories: null,
    source_url: '',
    source_name: '',
    is_favorite: false,
    status: 'draft',
    ingredients: [{ quantity_display: '', unit: '', name: '', note: '' }],
    steps: [{ instruction: '', timer_seconds: null }],
    images: [],
})

export default function RecipeFormPage({ recipe, categories, allTags }: FormPageProps) {
    const isNew = recipe === null

    const { data, setData, post, put, processing, errors, isDirty } = useForm<RecipeFormState>(
        recipe ?? emptyRecipe(),
    )

    const [confirmDelete, setConfirmDelete] = useState(false)

    /* ---------------------------------------------------------------------
     * Local draft autosave.
     *
     * Only for new recipes, and only in the browser: writing a long recipe and
     * losing it to an accidental refresh is the single most annoying thing a
     * form like this can do. An existing recipe is already saved on the
     * server, so it does not need this.
     *
     * The saved draft is read once during initialisation rather than in an
     * effect, so the offer to restore it is there on the very first paint.
     * ------------------------------------------------------------------- */
    const [restorable, setRestorable] = useState<RecipeFormState | null>(() => {
        if (!isNew) return null

        const saved = readLocal<RecipeFormState>(DRAFT_KEY)

        return saved && saved.title.trim() !== '' ? saved : null
    })

    useEffect(() => {
        if (!isNew || !isDirty) return

        const timeout = window.setTimeout(() => writeLocal(DRAFT_KEY, data), 800)
        return () => window.clearTimeout(timeout)
    }, [data, isDirty, isNew])

    const submit = (event: React.FormEvent) => {
        event.preventDefault()

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (isNew) removeLocal(DRAFT_KEY)
            },
        }

        if (isNew) post(adminRoutes.recipes, options)
        else put(adminRoutes.recipe(recipe.id), options)
    }

    return (
        <form onSubmit={submit}>
            <AdminLayout
                title={isNew ? 'New recipe' : data.title || 'Untitled recipe'}
                backTo={{ href: adminRoutes.recipes, label: 'Recipes' }}
                description={
                    isNew
                        ? 'Drafts are saved locally as you type, so a refresh will not lose your work.'
                        : undefined
                }
                actions={
                    <>
                        {!isNew && (
                            <Link
                                href={`/recipes/${recipe.slug}`}
                                className="hidden h-9 items-center gap-1.5 rounded-full px-3 text-[0.88rem] font-medium text-ink-muted transition hover:bg-surface-2 hover:text-ink sm:inline-flex"
                            >
                                <Eye className="size-4" aria-hidden="true" />
                                Preview
                            </Link>
                        )}

                        <Button type="submit" size="sm" disabled={processing}>
                            <Save className="size-4" aria-hidden="true" />
                            {processing ? 'Saving…' : 'Save'}
                        </Button>
                    </>
                }
            >
                {restorable && (
                    <div className="mb-5 flex flex-wrap items-center gap-3 rounded-2xl bg-adriatic-soft px-4 py-3.5">
                        <RotateCcw className="size-4 shrink-0 text-adriatic" aria-hidden="true" />
                        <p className="min-w-0 flex-1 text-[0.92rem] text-ink">
                            You have an unsaved draft of “{restorable.title}”.
                        </p>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                onClick={() => {
                                    for (const [key, value] of Object.entries(restorable)) {
                                        setData(key as keyof RecipeFormState, value as never)
                                    }
                                    setRestorable(null)
                                }}
                            >
                                Restore
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    removeLocal(DRAFT_KEY)
                                    setRestorable(null)
                                }}
                            >
                                Discard
                            </Button>
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

                {/* Sticky save bar — a long recipe should never require
                    scrolling back to the top to save. */}
                <div className="safe-dock sticky bottom-0 z-20 -mx-4 mt-6 border-t border-line bg-canvas/92 px-4 pt-3 backdrop-blur-xl sm:-mx-6 sm:px-6">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" disabled={processing} className="flex-1 sm:flex-none">
                            <Save className="size-4" aria-hidden="true" />
                            {processing ? 'Saving…' : isNew ? 'Create recipe' : 'Save changes'}
                        </Button>

                        {!isNew && (
                            <>
                                <Button
                                    variant="secondary"
                                    aria-label="Duplicate recipe"
                                    onClick={() =>
                                        router.post(
                                            adminRoutes.recipeDuplicate(recipe.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Copy className="size-4" aria-hidden="true" />
                                    <span className="hidden sm:inline">Duplicate</span>
                                </Button>

                                <Button
                                    variant="ghost"
                                    aria-label="Delete recipe"
                                    onClick={() => setConfirmDelete(true)}
                                    className="text-croatia"
                                >
                                    <Trash2 className="size-4" aria-hidden="true" />
                                    <span className="hidden sm:inline">Delete</span>
                                </Button>
                            </>
                        )}

                        <span className="ml-auto hidden text-[0.85rem] text-ink-muted sm:block">
                            {data.status === 'published' ? 'Visible to everyone' : 'Draft — not public'}
                        </span>
                    </div>
                </div>
            </AdminLayout>

            {!isNew && (
                <ConfirmDialog
                    open={confirmDelete}
                    title={`Delete “${data.title}”?`}
                    body="It moves to the trash, where it can be restored. Nothing is removed permanently."
                    confirmLabel="Move to trash"
                    destructive
                    onCancel={() => setConfirmDelete(false)}
                    onConfirm={() => {
                        setConfirmDelete(false)
                        router.delete(adminRoutes.recipe(recipe.id))
                    }}
                />
            )}
        </form>
    )
}
