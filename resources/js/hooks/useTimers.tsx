import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react'

export interface CookingTimer {
    id: string
    label: string
    /** Absolute epoch milliseconds at which the timer finishes. */
    endsAt: number
    durationSeconds: number
    remaining: number
    done: boolean
}

interface StoredTimer {
    id: string
    label: string
    endsAt: number
    durationSeconds: number
}

interface TimerContextValue {
    timers: CookingTimer[]
    start: (seconds: number, label: string) => void
    cancel: (id: string) => void
    dismiss: (id: string) => void
    clearAll: () => void
    notificationsEnabled: boolean
    requestNotifications: () => Promise<void>
    canRequestNotifications: boolean
}

const TimerContext = createContext<TimerContextValue | null>(null)

const STORAGE_KEY = 'ribs:timers'

function load(): StoredTimer[] {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY)
        if (!raw) return []

        const parsed = JSON.parse(raw) as StoredTimer[]
        // Drop anything that finished more than ten minutes ago.
        return parsed.filter((timer) => timer.endsAt > Date.now() - 600_000)
    } catch {
        return []
    }
}

function save(timers: StoredTimer[]): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(timers))
    } catch {
        // Timers still run in memory.
    }
}

/**
 * A short two-tone chime, synthesised rather than shipped as an audio file.
 *
 * Autoplay policies allow this because it only ever fires inside a session the
 * user started by pressing a timer button.
 */
function chime(): void {
    try {
        const AudioCtor =
            window.AudioContext ??
            (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
        if (!AudioCtor) return

        const context = new AudioCtor()
        const now = context.currentTime

        for (const [index, frequency] of [880, 1180].entries()) {
            const oscillator = context.createOscillator()
            const gain = context.createGain()

            oscillator.type = 'sine'
            oscillator.frequency.value = frequency

            const start = now + index * 0.26
            gain.gain.setValueAtTime(0.0001, start)
            gain.gain.exponentialRampToValueAtTime(0.32, start + 0.02)
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.55)

            oscillator.connect(gain).connect(context.destination)
            oscillator.start(start)
            oscillator.stop(start + 0.6)
        }

        window.setTimeout(() => void context.close(), 2000)
    } catch {
        // No audio available — the visual alert still fires.
    }
}

/**
 * Cooking timers.
 *
 * Held at the application root so they survive navigation between steps and
 * even between pages, and stored as absolute end times rather than countdowns
 * so a backgrounded phone comes back with the right number on the clock.
 *
 * Notifications are strictly optional: nothing here ever asks for permission
 * on its own, and every timer works fully without it.
 */
export function TimerProvider({ children }: { children: ReactNode }) {
    const [stored, setStored] = useState<StoredTimer[]>(() => (typeof window === 'undefined' ? [] : load()))
    const [now, setNow] = useState(() => Date.now())
    const notified = useRef(new Set<string>())

    const [permission, setPermission] = useState<NotificationPermission | 'unsupported'>(() =>
        typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'unsupported',
    )

    useEffect(() => {
        if (stored.length === 0) return

        const interval = window.setInterval(() => setNow(Date.now()), 250)
        return () => window.clearInterval(interval)
    }, [stored.length])

    useEffect(() => save(stored), [stored])

    const timers = useMemo<CookingTimer[]>(
        () =>
            stored.map((timer) => ({
                ...timer,
                remaining: Math.max(0, Math.round((timer.endsAt - now) / 1000)),
                done: timer.endsAt <= now,
            })),
        [stored, now],
    )

    // Fire exactly once per timer, even across re-renders.
    useEffect(() => {
        for (const timer of timers) {
            if (!timer.done || notified.current.has(timer.id)) continue

            notified.current.add(timer.id)
            chime()
            navigator.vibrate?.([220, 120, 220])

            if (permission === 'granted') {
                try {
                    new Notification('Timer finished', {
                        body: timer.label,
                        tag: timer.id,
                        icon: '/icons/icon-192.png',
                    })
                } catch {
                    // Some browsers require a service worker registration here.
                }
            }
        }
    }, [timers, permission])

    const start = useCallback((seconds: number, label: string) => {
        const timer: StoredTimer = {
            id: `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
            label,
            endsAt: Date.now() + seconds * 1000,
            durationSeconds: seconds,
        }

        setNow(Date.now())
        setStored((current) => [...current, timer].slice(-4))
    }, [])

    const remove = useCallback((id: string) => {
        notified.current.delete(id)
        setStored((current) => current.filter((timer) => timer.id !== id))
    }, [])

    const clearAll = useCallback(() => {
        notified.current.clear()
        setStored([])
    }, [])

    const requestNotifications = useCallback(async () => {
        if (typeof window === 'undefined' || !('Notification' in window)) return

        try {
            setPermission(await Notification.requestPermission())
        } catch {
            // Denied or unsupported; timers continue to chime.
        }
    }, [])

    const value = useMemo<TimerContextValue>(
        () => ({
            timers,
            start,
            cancel: remove,
            dismiss: remove,
            clearAll,
            notificationsEnabled: permission === 'granted',
            canRequestNotifications: permission === 'default',
            requestNotifications,
        }),
        [timers, start, remove, clearAll, permission, requestNotifications],
    )

    return <TimerContext.Provider value={value}>{children}</TimerContext.Provider>
}

export function useTimers(): TimerContextValue {
    const context = useContext(TimerContext)

    if (!context) {
        throw new Error('useTimers must be used inside a TimerProvider')
    }

    return context
}
