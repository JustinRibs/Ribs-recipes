import { Download, ImagePlus, Link2, Loader2, Star, Trash2, X } from 'lucide-react'
import { useRef, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { TextField } from '@/components/ui/Field'
import { cn } from '@/lib/cn'
import { adminRoutes } from '@/lib/routes'
import type { ImageFormRow, ImageResource } from '@/types'

interface ImagePickerProps {
    images: ImageFormRow[]
    onChange: (images: ImageFormRow[]) => void
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

/**
 * Hero photo and gallery.
 *
 * Photos upload the moment they are chosen, so the author sees a real
 * thumbnail immediately rather than waiting for the whole form to submit. The
 * recipe claims them when it is saved; anything abandoned is swept up by
 * `php artisan media:prune`.
 *
 * Remote photos can be copied onto the server or hot-linked — the choice
 * matters when importing, where the source image may disappear next year.
 */
export function ImagePicker({ images, onChange }: ImagePickerProps) {
    const [uploading, setUploading] = useState(false)
    const [error, setError] = useState<string | null>(null)
    const [remoteUrl, setRemoteUrl] = useState('')
    const [remoteOpen, setRemoteOpen] = useState(false)
    const fileInput = useRef<HTMLInputElement>(null)

    const append = (image: ImageResource) => {
        onChange([
            ...images,
            {
                ...image,
                caption: '',
                alt: image.alt ?? '',
                isHero: images.length === 0,
            },
        ])
    }

    const uploadFiles = async (files: FileList | null) => {
        if (!files || files.length === 0) return

        setUploading(true)
        setError(null)

        for (const file of Array.from(files).slice(0, 12)) {
            const body = new FormData()
            body.append('file', file)

            try {
                const response = await fetch(adminRoutes.media, {
                    method: 'POST',
                    body,
                    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                })

                const payload = (await response.json()) as {
                    image?: ImageResource
                    message?: string
                    errors?: Record<string, string[]>
                }

                if (!response.ok) {
                    throw new Error(
                        payload.errors?.file?.[0] ?? payload.message ?? 'That photo could not be uploaded.',
                    )
                }

                if (payload.image) append(payload.image)
            } catch (uploadError) {
                setError((uploadError as Error).message)
                break
            }
        }

        setUploading(false)
        if (fileInput.current) fileInput.current.value = ''
    }

    const addRemote = async (mode: 'download' | 'link') => {
        if (!remoteUrl.trim()) return

        setUploading(true)
        setError(null)

        try {
            const response = await fetch(adminRoutes.mediaRemote, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ url: remoteUrl.trim(), mode }),
            })

            const payload = (await response.json()) as {
                image?: ImageResource
                message?: string
                errors?: Record<string, string[]>
            }

            if (!response.ok) {
                throw new Error(
                    payload.errors?.url?.[0] ?? payload.message ?? 'That image could not be added.',
                )
            }

            if (payload.image) {
                append(payload.image)
                setRemoteUrl('')
                setRemoteOpen(false)
            }
        } catch (remoteError) {
            setError((remoteError as Error).message)
        } finally {
            setUploading(false)
        }
    }

    const update = (id: number, patch: Partial<ImageFormRow>) => {
        onChange(images.map((image) => (image.id === id ? { ...image, ...patch } : image)))
    }

    const setHero = (id: number) => {
        onChange(
            images
                .map((image) => ({ ...image, isHero: image.id === id }))
                // The hero is always first, which is also the order the server
                // stores and the gallery renders.
                .sort((a, b) => Number(b.isHero) - Number(a.isHero)),
        )
    }

    const remove = (id: number) => onChange(images.filter((image) => image.id !== id))

    return (
        <div className="space-y-4">
            {images.length > 0 && (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {images.map((image) => (
                        <li
                            key={image.id}
                            className={cn(
                                'group relative overflow-hidden rounded-xl bg-surface-2 ring-2 transition',
                                image.isHero ? 'ring-adriatic' : 'ring-transparent',
                            )}
                        >
                            <img
                                src={image.thumb ?? image.src}
                                alt={image.alt || ''}
                                loading="lazy"
                                className="aspect-square w-full object-cover"
                            />

                            {image.isHero && (
                                <span className="absolute left-2 top-2 rounded-full bg-adriatic px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-white">
                                    Hero
                                </span>
                            )}

                            {image.source === 'remote' && (
                                <span
                                    className="absolute right-2 top-2 rounded-full bg-black/55 p-1 text-white"
                                    title="Hot-linked from its original location"
                                >
                                    <Link2 className="size-3" aria-hidden="true" />
                                </span>
                            )}

                            <div className="flex gap-1 p-1.5">
                                {!image.isHero && (
                                    <button
                                        type="button"
                                        onClick={() => setHero(image.id)}
                                        className="flex h-10 flex-1 items-center justify-center gap-1 rounded-lg bg-surface text-[0.78rem] font-medium text-ink transition hover:bg-surface-3"
                                    >
                                        <Star className="size-3.5" aria-hidden="true" />
                                        Hero
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => remove(image.id)}
                                    aria-label="Remove photo"
                                    className={cn(
                                        'flex h-10 items-center justify-center rounded-lg bg-surface text-ink-muted transition hover:bg-croatia-soft hover:text-croatia',
                                        image.isHero ? 'flex-1' : 'w-10',
                                    )}
                                >
                                    <Trash2 className="size-3.5" aria-hidden="true" />
                                </button>
                            </div>

                            <div className="space-y-1 px-1.5 pb-1.5">
                                <input
                                    value={image.alt}
                                    onChange={(event) => update(image.id, { alt: event.target.value })}
                                    placeholder="Alt text"
                                    aria-label="Alt text"
                                    className="h-9 w-full rounded-lg bg-surface px-2 text-[0.78rem] text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-adriatic"
                                />
                                <input
                                    value={image.caption}
                                    onChange={(event) => update(image.id, { caption: event.target.value })}
                                    placeholder="Caption"
                                    aria-label="Caption"
                                    className="h-9 w-full rounded-lg bg-surface px-2 text-[0.78rem] text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-adriatic"
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap gap-2">
                <input
                    ref={fileInput}
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={(event) => void uploadFiles(event.target.files)}
                    className="sr-only"
                    id="recipe-photos"
                />

                <label
                    htmlFor="recipe-photos"
                    className="inline-flex h-11 cursor-pointer items-center gap-2 rounded-full bg-surface-2 px-5 text-[0.92rem] font-medium text-ink transition hover:bg-surface-3"
                >
                    {uploading ? (
                        <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                    ) : (
                        <ImagePlus className="size-4" aria-hidden="true" />
                    )}
                    {uploading ? 'Uploading…' : 'Add photos'}
                </label>

                <Button variant="ghost" onClick={() => setRemoteOpen((open) => !open)}>
                    <Link2 className="size-4" aria-hidden="true" />
                    From a URL
                </Button>
            </div>

            {remoteOpen && (
                <div className="space-y-3 rounded-xl bg-surface-2 p-4">
                    <TextField
                        label="Image address"
                        type="url"
                        inputMode="url"
                        value={remoteUrl}
                        onChange={(event) => setRemoteUrl(event.target.value)}
                        placeholder="https://example.com/photo.jpg"
                        hint="Only public http(s) addresses are fetched, and the file is checked before it is accepted."
                    />

                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" disabled={uploading} onClick={() => void addRemote('download')}>
                            <Download className="size-4" aria-hidden="true" />
                            Download and store
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={uploading}
                            onClick={() => void addRemote('link')}
                        >
                            <Link2 className="size-4" aria-hidden="true" />
                            Use external image
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setRemoteOpen(false)}>
                            <X className="size-4" aria-hidden="true" />
                            Cancel
                        </Button>
                    </div>

                    <p className="text-[0.82rem] text-ink-muted">
                        Storing locally survives the source site changing or disappearing. Hot-linking saves
                        disk space but depends on that site staying up.
                    </p>
                </div>
            )}

            {error && (
                <p className="rounded-xl bg-croatia-soft px-4 py-3 text-[0.9rem] text-croatia" role="alert">
                    {error}
                </p>
            )}
        </div>
    )
}
