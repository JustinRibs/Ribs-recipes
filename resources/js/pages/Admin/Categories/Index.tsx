import { router, useForm } from '@inertiajs/react'
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { TextAreaField, TextField } from '@/components/ui/Field'
import { Sheet } from '@/components/ui/Sheet'
import { AdminLayout } from '@/layouts/AdminLayout'
import { adminRoutes } from '@/lib/routes'

interface CategoryRow {
    id: number
    name: string
    slug: string
    description: string | null
    icon: string | null
    color: string | null
    sortOrder: number
    recipesCount: number
    url: string
}

interface CategoriesProps {
    categories: CategoryRow[]
}

interface CategoryFormShape {
    name: string
    slug: string
    description: string
    color: string
    sort_order: number | null
    [key: string]: string | number | null
}

export default function CategoriesIndex({ categories }: CategoriesProps) {
    const [editing, setEditing] = useState<CategoryRow | 'new' | null>(null)
    const [confirm, setConfirm] = useState<CategoryRow | null>(null)

    const reorder = (index: number, direction: -1 | 1) => {
        const target = index + direction
        if (target < 0 || target >= categories.length) return

        const ids = categories.map((category) => category.id)
        const [moved] = ids.splice(index, 1)
        if (moved !== undefined) ids.splice(target, 0, moved)

        router.post(adminRoutes.categories + '/reorder', { ids }, { preserveScroll: true })
    }

    return (
        <AdminLayout
            title="Categories"
            description="The top-level sections of the collection. Order here is the order they appear on the site."
            actions={
                <Button size="sm" aria-label="New category" onClick={() => setEditing('new')}>
                    <Plus className="size-4" aria-hidden="true" />
                    <span className="hidden sm:inline">New category</span>
                </Button>
            }
        >
            <ul className="space-y-2">
                {categories.map((category, index) => (
                    <li
                        key={category.id}
                        className="flex items-center gap-3 rounded-2xl bg-surface p-3 shadow-[var(--shadow-card)] ring-1 ring-line"
                    >
                        <span
                            className="size-10 shrink-0 rounded-xl"
                            style={{ backgroundColor: category.color ?? 'var(--color-surface-2)' }}
                            aria-hidden="true"
                        />

                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium text-ink">{category.name}</p>
                            <p className="truncate text-[0.82rem] text-ink-muted">
                                /{category.slug} · {category.recipesCount}{' '}
                                {category.recipesCount === 1 ? 'recipe' : 'recipes'}
                            </p>
                        </div>

                        <div className="flex shrink-0 items-center gap-0.5">
                            <IconAction
                                label={`Move ${category.name} up`}
                                onClick={() => reorder(index, -1)}
                                disabled={index === 0}
                            >
                                <ChevronUp className="size-4" aria-hidden="true" />
                            </IconAction>
                            <IconAction
                                label={`Move ${category.name} down`}
                                onClick={() => reorder(index, 1)}
                                disabled={index === categories.length - 1}
                            >
                                <ChevronDown className="size-4" aria-hidden="true" />
                            </IconAction>
                            <IconAction label={`Edit ${category.name}`} onClick={() => setEditing(category)}>
                                <Pencil className="size-4" aria-hidden="true" />
                            </IconAction>
                            <IconAction
                                label={`Delete ${category.name}`}
                                onClick={() => setConfirm(category)}
                                destructive
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                            </IconAction>
                        </div>
                    </li>
                ))}
            </ul>

            {categories.length === 0 && (
                <div className="rounded-2xl border border-dashed border-line-strong py-16 text-center">
                    <p className="text-ink-muted">No categories yet.</p>
                </div>
            )}

            {editing && (
                <CategorySheet
                    category={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}

            <ConfirmDialog
                open={confirm !== null}
                title={`Delete “${confirm?.name}”?`}
                body={
                    confirm && confirm.recipesCount > 0
                        ? `Its ${confirm.recipesCount} ${confirm.recipesCount === 1 ? 'recipe stays' : 'recipes stay'} — they simply become uncategorised.`
                        : 'This category has no recipes.'
                }
                confirmLabel="Delete category"
                destructive
                onCancel={() => setConfirm(null)}
                onConfirm={() => {
                    if (confirm) router.delete(adminRoutes.category(confirm.id), { preserveScroll: true })
                    setConfirm(null)
                }}
            />
        </AdminLayout>
    )
}

function CategorySheet({ category, onClose }: { category: CategoryRow | null; onClose: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm<CategoryFormShape>({
        name: category?.name ?? '',
        slug: category?.slug ?? '',
        description: category?.description ?? '',
        color: category?.color ?? '#2F6F9F',
        sort_order: category?.sortOrder ?? null,
    })

    const submit = (event: React.FormEvent) => {
        event.preventDefault()

        const options = { preserveScroll: true, onSuccess: onClose }

        if (category) put(adminRoutes.category(category.id), options)
        else post(adminRoutes.categories, options)
    }

    return (
        <Sheet
            open
            onClose={onClose}
            title={category ? `Edit ${category.name}` : 'New category'}
            footer={
                <div className="flex gap-2">
                    <Button variant="secondary" className="flex-1" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="category-form" className="flex-1" disabled={processing}>
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                </div>
            }
        >
            <form id="category-form" onSubmit={submit} className="space-y-4">
                <TextField
                    label="Name"
                    required
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    error={errors.name}
                    autoFocus
                />

                <TextField
                    label="Web address"
                    value={data.slug}
                    onChange={(event) => setData('slug', event.target.value)}
                    error={errors.slug}
                    placeholder="breakfast"
                    hint="Leave blank to generate it from the name."
                />

                <TextAreaField
                    label="Description"
                    value={data.description}
                    onChange={(event) => setData('description', event.target.value)}
                    error={errors.description}
                    rows={2}
                    hint="Shown under the heading on the category page."
                />

                <div className="space-y-1.5">
                    <label htmlFor="category-color" className="block text-sm font-medium text-ink">
                        Accent colour
                    </label>
                    <div className="flex items-center gap-3">
                        <input
                            id="category-color"
                            type="color"
                            value={data.color}
                            onChange={(event) => setData('color', event.target.value)}
                            className="h-11 w-16 cursor-pointer rounded-xl bg-surface ring-1 ring-line-strong"
                        />
                        <span className="font-mono text-[0.88rem] text-ink-muted">{data.color}</span>
                    </div>
                    {errors.color && (
                        <p className="text-sm text-croatia" role="alert">
                            {errors.color}
                        </p>
                    )}
                </div>
            </form>
        </Sheet>
    )
}

function IconAction({
    label,
    onClick,
    disabled,
    destructive,
    children,
}: {
    label: string
    onClick: () => void
    disabled?: boolean
    destructive?: boolean
    children: React.ReactNode
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            title={label}
            className={
                'flex size-10 items-center justify-center rounded-xl text-ink-faint transition disabled:opacity-25 ' +
                (destructive
                    ? 'hover:bg-croatia-soft hover:text-croatia'
                    : 'hover:bg-surface-2 hover:text-ink')
            }
        >
            {children}
        </button>
    )
}
