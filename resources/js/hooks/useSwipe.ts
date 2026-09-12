import { useRef, type PointerEvent as ReactPointerEvent } from 'react'

interface SwipeOptions {
    onSwipeLeft?: () => void
    onSwipeRight?: () => void
    /** Minimum horizontal travel, in pixels, before it counts. */
    threshold?: number
}

/**
 * Horizontal swipe detection for cooking mode.
 *
 * Pointer events rather than touch events, so a trackpad drag and a stylus
 * work the same as a thumb. A gesture is ignored unless it is clearly
 * horizontal, which keeps vertical page scrolling completely unaffected — the
 * most common way a home-made swipe handler ruins a page.
 */
export function useSwipe({ onSwipeLeft, onSwipeRight, threshold = 56 }: SwipeOptions) {
    const start = useRef<{ x: number; y: number; time: number } | null>(null)

    const onPointerDown = (event: ReactPointerEvent) => {
        if (event.pointerType === 'mouse' && event.button !== 0) return

        start.current = { x: event.clientX, y: event.clientY, time: Date.now() }
    }

    const onPointerUp = (event: ReactPointerEvent) => {
        const origin = start.current
        start.current = null

        if (!origin) return

        const dx = event.clientX - origin.x
        const dy = event.clientY - origin.y
        const elapsed = Date.now() - origin.time

        // Must be fast, long enough, and more horizontal than vertical.
        if (elapsed > 800) return
        if (Math.abs(dx) < threshold) return
        if (Math.abs(dx) < Math.abs(dy) * 1.6) return

        if (dx < 0) onSwipeLeft?.()
        else onSwipeRight?.()
    }

    const onPointerCancel = () => {
        start.current = null
    }

    return { onPointerDown, onPointerUp, onPointerCancel }
}
