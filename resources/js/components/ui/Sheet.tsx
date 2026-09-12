import { useEffect, useRef, type ReactNode } from 'react'
import { X } from 'lucide-react'
import { cn } from '@/lib/cn'
import { IconButton } from './Button'

interface SheetProps {
    open: boolean
    onClose: () => void
    title: string
    children: ReactNode
    /** Bottom sheet on phones, side panel from `md` up. */
    side?: 'bottom' | 'right'
    footer?: ReactNode
    className?: string
}

/**
 * A modal panel that behaves like a native sheet on a phone.
 *
 * It is a real <dialog>, so focus trapping, Escape and inertness of the page
 * behind it come from the platform rather than from hand-written key handlers
 * that usually get one of the three wrong.
 */
export function Sheet({ open, onClose, title, children, side = 'bottom', footer, className }: SheetProps) {
    const ref = useRef<HTMLDialogElement>(null)

    useEffect(() => {
        const dialog = ref.current
        if (!dialog) return

        if (open && !dialog.open) {
            dialog.showModal()
            // Stop the page behind from scrolling under the sheet on iOS.
            document.body.style.overflow = 'hidden'
        } else if (!open && dialog.open) {
            dialog.close()
            document.body.style.overflow = ''
        }

        return () => {
            document.body.style.overflow = ''
        }
    }, [open])

    useEffect(() => {
        const dialog = ref.current
        if (!dialog) return

        const onCancel = (event: Event) => {
            event.preventDefault()
            onClose()
        }

        dialog.addEventListener('cancel', onCancel)
        return () => dialog.removeEventListener('cancel', onCancel)
    }, [onClose])

    return (
        <dialog
            ref={ref}
            aria-label={title}
            onClick={(event) => {
                // Clicking the backdrop (the dialog element itself) closes.
                if (event.target === ref.current) onClose()
            }}
            className={cn(
                'm-0 max-h-dvh w-full max-w-none bg-transparent p-0 backdrop:bg-black/45 backdrop:backdrop-blur-[2px]',
                side === 'bottom' ? 'mt-auto' : 'ml-auto h-dvh md:max-w-md',
                'open:animate-none',
            )}
        >
            <div
                className={cn(
                    'flex flex-col bg-surface text-ink shadow-[var(--shadow-overlay)]',
                    side === 'bottom'
                        ? 'max-h-[88dvh] rounded-t-[1.75rem] sm:mx-auto sm:max-w-lg sm:rounded-b-[1.75rem] sm:mb-6'
                        : 'h-dvh',
                    className,
                )}
            >
                <header className="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                    {side === 'bottom' && (
                        <span
                            aria-hidden="true"
                            className="absolute left-1/2 top-2 h-1 w-10 -translate-x-1/2 rounded-full bg-surface-3"
                        />
                    )}
                    <h2 className="truncate text-lg font-semibold tracking-tight">{title}</h2>
                    <IconButton label="Close" onClick={onClose} variant="quiet" className="size-10">
                        <X className="size-5" aria-hidden="true" />
                    </IconButton>
                </header>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4">{children}</div>

                {footer && <div className="safe-dock border-t border-line px-5 pt-3">{footer}</div>}
            </div>
        </dialog>
    )
}
