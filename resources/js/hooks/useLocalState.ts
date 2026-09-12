import { useCallback, useEffect, useState } from 'react'

function read<T>(key: string, fallback: T): T {
    if (typeof window === 'undefined') return fallback

    try {
        const raw = window.localStorage.getItem(key)

        return raw === null ? fallback : (JSON.parse(raw) as T)
    } catch {
        return fallback
    }
}

/**
 * State mirrored into localStorage.
 *
 * Used for the things that should survive a refresh but never belong on the
 * server: which ingredients have been gathered, which steps are done, and the
 * recipe editor's draft autosave.
 *
 * Every access is guarded — Safari's private mode throws on write, and a
 * cooking session must not die because of it.
 */
export function useLocalState<T>(key: string, initial: T) {
    const [value, setValue] = useState<T>(() => read(key, initial))

    // Adjusting state during render, rather than in an effect, is React's own
    // answer to "a prop changed and this state derives from it" — it avoids
    // the extra render an effect would cause, and the flash of the previous
    // recipe's checklist that comes with it.
    const [seenKey, setSeenKey] = useState(key)

    if (seenKey !== key) {
        setSeenKey(key)
        setValue(read(key, initial))
    }

    useEffect(() => {
        try {
            window.localStorage.setItem(key, JSON.stringify(value))
        } catch {
            // Storage unavailable or full — keep working in memory.
        }
    }, [key, value])

    const clear = useCallback(() => {
        setValue(initial)

        try {
            window.localStorage.removeItem(key)
        } catch {
            // Nothing to clean up.
        }
    }, [key, initial])

    return [value, setValue, clear] as const
}

/** Read a stored value once, without subscribing. */
export function readLocal<T>(key: string): T | null {
    try {
        const raw = window.localStorage.getItem(key)
        return raw === null ? null : (JSON.parse(raw) as T)
    } catch {
        return null
    }
}

export function writeLocal(key: string, value: unknown): void {
    try {
        window.localStorage.setItem(key, JSON.stringify(value))
    } catch {
        // Ignore.
    }
}

export function removeLocal(key: string): void {
    try {
        window.localStorage.removeItem(key)
    } catch {
        // Ignore.
    }
}
