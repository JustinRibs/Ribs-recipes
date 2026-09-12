import { ChevronLeft, ChevronRight, X } from 'lucide-react'
import { useCallback, useEffect, useRef } from 'react'
import { useSwipe } from '@/hooks/useSwipe'
import type { ImageResource } from '@/types'

interface LightboxProps {
    images: ImageResource[]
    index: number | null
    onClose: () => void
    onNavigate: (index: number) => void
}

/**
 * Full-screen photo viewer.
 *
 * Roughly forty lines instead of a gallery dependency: a <dialog> for focus
 * management, arrow keys and swipes for navigation, and the same responsive
 * image the page already downloaded so opening it costs nothing.
 */
export function Lightbox({ images, index, onClose, onNavigate }: LightboxProps) {
    const ref = useRef<HTMLDialogElement>(null)
    const open = index !== null
    const image = index === null ? null : images[index]

    const go = useCallback(
        (delta: number) => {
            if (index === null || images.length === 0) return
            onNavigate((index + delta + images.length) % images.length)
        },
        [index, images.length, onNavigate],
    )

    const swipe = useSwipe({ onSwipeLeft: () => go(1), onSwipeRight: () => go(-1) })

    useEffect(() => {
        const dialog = ref.current
        if (!dialog) return

        if (open && !dialog.open) dialog.showModal()
        else if (!open && dialog.open) dialog.close()
    }, [open])

    useEffect(() => {
        const dialog = ref.current
        if (!dialog) return

        const onCancel = (event: Event) => {
            event.preventDefault()
            onClose()
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowRight') go(1)
            if (event.key === 'ArrowLeft') go(-1)
        }

        dialog.addEventListener('cancel', onCancel)
        dialog.addEventListener('keydown', onKey)

        return () => {
            dialog.removeEventListener('cancel', onCancel)
            dialog.removeEventListener('keydown', onKey)
        }
    }, [go, onClose])

    return (
        <dialog
            ref={ref}
            aria-label="Photo viewer"
            className="m-0 h-dvh max-h-none w-dvw max-w-none bg-black/94 p-0 backdrop:bg-black/80"
            onClick={(event) => {
                if (event.target === ref.current) onClose()
            }}
        >
            {image && (
                <div className="flex h-full flex-col" {...swipe}>
                    <div className="safe-top flex items-center justify-between px-4 py-3">
                        <span className="text-sm tabular-nums text-white/70">
                            {(index ?? 0) + 1} / {images.length}
                        </span>
                        <button
                            type="button"
                            onClick={onClose}
                            aria-label="Close photo viewer"
                            className="flex size-11 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10 hover:text-white"
                        >
                            <X className="size-6" aria-hidden="true" />
                        </button>
                    </div>

                    <div className="flex min-h-0 flex-1 items-center justify-center px-3">
                        <img
                            src={image.src}
                            srcSet={image.srcset ?? undefined}
                            sizes="100vw"
                            alt={image.alt ?? ''}
                            className="max-h-full max-w-full rounded-lg object-contain"
                        />
                    </div>

                    <div className="safe-bottom flex items-center justify-between gap-4 px-4 py-4">
                        {images.length > 1 ? (
                            <button
                                type="button"
                                onClick={() => go(-1)}
                                aria-label="Previous photo"
                                className="flex size-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                            >
                                <ChevronLeft className="size-6" aria-hidden="true" />
                            </button>
                        ) : (
                            <span />
                        )}

                        {image.caption && (
                            <p className="flex-1 text-center text-sm text-white/80">{image.caption}</p>
                        )}

                        {images.length > 1 ? (
                            <button
                                type="button"
                                onClick={() => go(1)}
                                aria-label="Next photo"
                                className="flex size-12 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                            >
                                <ChevronRight className="size-6" aria-hidden="true" />
                            </button>
                        ) : (
                            <span />
                        )}
                    </div>
                </div>
            )}
        </dialog>
    )
}
