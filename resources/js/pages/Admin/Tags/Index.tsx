import { router, useForm } from '@inertiajs/react'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { TextField } from '@/components/ui/Field'
import { Sheet } from '@/components/ui/Sheet'
import { AdminLayout } from '@/layouts/AdminLayout'
import { adminRoutes } from '@/lib/routes'

interface TagRow {
    id: number
    name: string
    slug: string
    recipesCount: number
    url: string
}

interface TagFormShape {
    name: string
    slug: string
    [key: string]: string
}

export default function TagsIndex({ tags }: { tags: TagRow[] }) {
    const [editing, setEditing] = useState<TagRow | 'new' | null>(null)
    const [confirm, setConfirm] = useState<TagRow | null>(null)

    const { data, setData, post, processing, errors, reset } = useForm<TagFormShape>({ name: '', slug: '' })

    const create = (event: React.FormEvent) => {
        event.preventDefault()
        post(adminRoutes.tags, { preserveScroll: true, onSuccess: () => reset() })
    }

    return (
        <AdminLayout
            title="Tags"
            description="Cross-cutting labels — a recipe can carry as many as it needs."
        >
            <form
                onSubmit={create}
                className="flex flex-wrap items-end gap-2 rounded-2xl bg-surface p-4 ring-1 ring-line"
            >
                <TextField
                    label="Add a tag"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    error={errors.name}
                    placeholder="Weeknight"
                    wrapperClassName="min-w-48 flex-1"
                />
                <Button type="submit" disabled={processing || !data.name.trim()}>
                    <Plus className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </form>

            {tags.length === 0 ? (
                <div className="mt-6 rounded-2xl border border-dashed border-line-strong py-16 text-center">
                    <p className="text-ink-muted">No tags yet.</p>
                </div>
            ) : (
                <ul className="mt-6 flex flex-wrap gap-2">
                    {tags.map((tag) => (
                        <li
                            key={tag.id}
                            className="flex items-center gap-1 rounded-full bg-surface py-1.5 pl-4 pr-1.5 shadow-[var(--shadow-card)] ring-1 ring-line"
                        >
                            <span className="font-medium text-ink">{tag.name}</span>
                            <span className="ml-1 text-[0.8rem] tabular-nums text-ink-faint">
                                {tag.recipesCount}
                            </span>

                            <button
                                type="button"
                                onClick={() => setEditing(tag)}
                                aria-label={`Edit ${tag.name}`}
                                className="ml-1 flex size-10 items-center justify-center rounded-full text-ink-faint transition hover:bg-surface-2 hover:text-ink"
                            >
                                <Pencil className="size-3.5" aria-hidden="true" />
                            </button>

                            <button
                                type="button"
                                onClick={() => setConfirm(tag)}
                                aria-label={`Delete ${tag.name}`}
                                className="flex size-10 items-center justify-center rounded-full text-ink-faint transition hover:bg-croatia-soft hover:text-croatia"
                            >
                                <Trash2 className="size-3.5" aria-hidden="true" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {editing && editing !== 'new' && <TagSheet tag={editing} onClose={() => setEditing(null)} />}

            <ConfirmDialog
                open={confirm !== null}
                title={`Delete “${confirm?.name}”?`}
                body={
                    confirm && confirm.recipesCount > 0
                        ? `It will be removed from ${confirm.recipesCount} ${confirm.recipesCount === 1 ? 'recipe' : 'recipes'}. The recipes themselves are untouched.`
                        : 'This tag is not used by any recipe.'
                }
                confirmLabel="Delete tag"
                destructive
                onCancel={() => setConfirm(null)}
                onConfirm={() => {
                    if (confirm) router.delete(adminRoutes.tag(confirm.id), { preserveScroll: true })
                    setConfirm(null)
                }}
            />
        </AdminLayout>
    )
}

function TagSheet({ tag, onClose }: { tag: TagRow; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm<TagFormShape>({
        name: tag.name,
        slug: tag.slug,
    })

    const submit = (event: React.FormEvent) => {
        event.preventDefault()
        put(adminRoutes.tag(tag.id), { preserveScroll: true, onSuccess: onClose })
    }

    return (
        <Sheet
            open
            onClose={onClose}
            title={`Edit ${tag.name}`}
            footer={
                <div className="flex gap-2">
                    <Button variant="secondary" className="flex-1" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="tag-form" className="flex-1" disabled={processing}>
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                </div>
            }
        >
            <form id="tag-form" onSubmit={submit} className="space-y-4">
                <TextField
                    label="Name"
                    required
                    autoFocus
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    error={errors.name}
                />
                <TextField
                    label="Web address"
                    value={data.slug}
                    onChange={(event) => setData('slug', event.target.value)}
                    error={errors.slug}
                    hint="Changing this breaks existing links to the tag page."
                />{' '}
            </form>
        </Sheet>
    )
}
