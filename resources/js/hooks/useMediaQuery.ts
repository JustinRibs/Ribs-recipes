import { useCallback, useSyncExternalStore } from 'react'

/**
 * Subscribes to a media query.
 *
 * useSyncExternalStore rather than useState + useEffect: the browser's media
 * query list *is* an external store, and reading it this way means the first
 * render already has the right answer instead of correcting itself a frame
 * later.
 */
export function useMediaQuery(query: string): boolean {
    const subscribe = useCallback(
        (onChange: () => void) => {
            const list = window.matchMedia(query)
            list.addEventListener('change', onChange)

            return () => list.removeEventListener('change', onChange)
        },
        [query],
    )

    return useSyncExternalStore(
        subscribe,
        () => window.matchMedia(query).matches,
        // Server-rendered HTML cannot know; assume the query does not match.
        () => false,
    )
}

export const usePrefersReducedMotion = () => useMediaQuery('(prefers-reduced-motion: reduce)')
