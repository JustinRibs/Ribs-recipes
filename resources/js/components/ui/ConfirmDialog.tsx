import { useEffect, useRef } from 'react'
import { Button } from './Button'

interface ConfirmDialogProps {
    open: boolean
    title: string
    body?: string
    confirmLabel?: string
    cancelLabel?: string
    destructive?: boolean
    onConfirm: () => void
    onCancel: () => void
}

/**
 * Confirmation for anything irreversible. A real <dialog>, so Escape works and
 * focus is trapped; the confirm button is focused on open so a keyboard user
 * can answer immediately.
 */
export function ConfirmDialog({
    open,
    title,
    body,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    destructive = false,
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    const ref = useRef<HTMLDialogElement>(null)
    const confirmRef = useRef<HTMLButtonElement>(null)

    useEffect(() => {
        const dialog = ref.current
        if (!dialog) return

        if (open && !dialog.open) {
            dialog.showModal()
            confirmRef.current?.focus()
        } else if (!open && dialog.open) {
            dialog.close()
        }
    }, [open])

    useEffect(() => {
        const dialog = ref.current
        if (!dialog) return

        const onCancelEvent = (event: Event) => {
            event.preventDefault()
            onCancel()
        }

        dialog.addEventListener('cancel', onCancelEvent)
        return () => dialog.removeEventListener('cancel', onCancelEvent)
    }, [onCancel])

    return (
        <dialog
            ref={ref}
            aria-labelledby="confirm-title"
            onClick={(event) => {
                if (event.target === ref.current) onCancel()
            }}
            className="m-auto w-[min(26rem,calc(100vw-2rem))] rounded-2xl bg-surface p-0 text-ink shadow-[var(--shadow-overlay)] backdrop:bg-black/45"
        >
            <div className="p-6">
                <h2 id="confirm-title" className="text-lg font-semibold tracking-tight">
                    {title}
                </h2>
                {body && <p className="mt-2 text-[0.95rem] leading-relaxed text-ink-muted">{body}</p>}

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={onCancel}>
                        {cancelLabel}
                    </Button>
                    <Button ref={confirmRef} variant={destructive ? 'danger' : 'primary'} onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </dialog>
    )
}
