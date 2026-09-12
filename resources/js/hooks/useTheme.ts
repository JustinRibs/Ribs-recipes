import { useCallback, useEffect, useState } from 'react'

export type ThemeMode = 'light' | 'dark' | 'system'

const STORAGE_KEY = 'ribs:theme'

function readStored(): ThemeMode {
    if (typeof window === 'undefined') return 'system'

    try {
        const value = window.localStorage.getItem(STORAGE_KEY)
        return value === 'light' || value === 'dark' ? value : 'system'
    } catch {
        return 'system'
    }
}

function resolve(mode: ThemeMode): 'light' | 'dark' {
    if (mode !== 'system') return mode
    if (typeof window === 'undefined') return 'light'

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function apply(mode: ThemeMode): void {
    const resolved = resolve(mode)
    document.documentElement.dataset.theme = resolved
    document.documentElement.dataset.themeMode = mode

    // Keeps the iOS status bar and Android toolbar in step with the page.
    const meta = document.querySelector<HTMLMetaElement>('meta[name="theme-color"]:not([media])')
    if (meta) meta.content = resolved === 'dark' ? '#0D1015' : '#FBFAF7'
}

/**
 * Light / dark / system, remembered locally.
 *
 * The initial value is written by an inline script in the document head, so
 * this hook adopts what is already on the element rather than causing a flash
 * on hydration. When the mode is "system" it follows the OS live.
 */
export function useTheme() {
    const [mode, setMode] = useState<ThemeMode>(readStored)

    useEffect(() => {
        apply(mode)

        try {
            if (mode === 'system') {
                window.localStorage.removeItem(STORAGE_KEY)
            } else {
                window.localStorage.setItem(STORAGE_KEY, mode)
            }
        } catch {
            // Private browsing — the choice simply will not persist.
        }
    }, [mode])

    useEffect(() => {
        if (mode !== 'system') return

        const query = window.matchMedia('(prefers-color-scheme: dark)')
        const listener = () => apply('system')

        query.addEventListener('change', listener)
        return () => query.removeEventListener('change', listener)
    }, [mode])

    const cycle = useCallback(() => {
        setMode((current) => (current === 'light' ? 'dark' : current === 'dark' ? 'system' : 'light'))
    }, [])

    return { mode, setMode, cycle, resolved: resolve(mode) }
}
