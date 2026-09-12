import { useCallback, useEffect, useRef, useState } from 'react'

type WakeLockSentinelLike = { released: boolean; release: () => Promise<void> }

/**
 * Keeps the screen awake while cooking.
 *
 * The Screen Wake Lock API is not available everywhere (notably older iOS and
 * any non-secure context), so this degrades silently: cooking mode works
 * exactly the same, the screen simply dims as usual.
 *
 * Browsers drop the lock whenever the tab is hidden, so it is re-acquired on
 * the next visibilitychange — otherwise the screen starts sleeping again the
 * moment someone glances at a message and comes back.
 */
export function useWakeLock(active: boolean) {
    const sentinel = useRef<WakeLockSentinelLike | null>(null)
    const [held, setHeld] = useState(false)

    const supported =
        typeof navigator !== 'undefined' && 'wakeLock' in navigator && typeof window !== 'undefined'

    const release = useCallback(async () => {
        const current = sentinel.current
        sentinel.current = null

        if (current && !current.released) {
            try {
                await current.release()
            } catch {
                // Already gone; nothing to do.
            }
        }

        // Reported only once the lock is actually gone, so the UI never claims
        // the screen is awake while it is being handed back.
        setHeld(false)
    }, [])

    const acquire = useCallback(async () => {
        if (!supported || sentinel.current || document.visibilityState !== 'visible') return

        const api = (
            navigator as Navigator & {
                wakeLock: { request: (type: 'screen') => Promise<WakeLockSentinelLike> }
            }
        ).wakeLock

        // Every state update below happens after this await, so nothing here
        // can fire synchronously from the effect that calls it.
        let lock: WakeLockSentinelLike | null

        try {
            lock = await api.request('screen')
        } catch {
            // Refused — a background tab, a low battery, or an unsupported
            // platform lying about support. Cooking mode works regardless.
            lock = null
        }

        if (lock === null) {
            setHeld(false)

            return
        }

        sentinel.current = lock
        setHeld(true)

        // Some platforms release without telling us; reflect it in the UI.
        ;(lock as unknown as EventTarget).addEventListener?.('release', () => {
            sentinel.current = null
            setHeld(false)
        })
    }, [supported])

    useEffect(() => {
        if (!active) {
            return
        }

        // `held` is only ever written after the wake-lock promise settles, so
        // no state changes synchronously here. The rule cannot see through the
        // async call, and deferring it behind a timeout purely to satisfy the
        // analysis would add a frame of delay for nothing.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        void acquire()

        const onVisibility = () => {
            if (document.visibilityState === 'visible') void acquire()
        }

        document.addEventListener('visibilitychange', onVisibility)

        // Releasing belongs in the cleanup: it covers leaving cooking mode,
        // unmounting, and `active` flipping back to false, in one place.
        return () => {
            document.removeEventListener('visibilitychange', onVisibility)
            void release()
        }
    }, [active, acquire, release])

    return { supported, held }
}
