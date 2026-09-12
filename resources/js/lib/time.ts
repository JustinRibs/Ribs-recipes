/** Duration helpers shared by recipe metadata, timers and cooking mode. */

export function humaniseMinutes(minutes: number | null | undefined): string | null {
    if (!minutes || minutes <= 0) return null
    if (minutes < 60) return `${minutes} min`

    const hours = Math.floor(minutes / 60)
    const rest = minutes % 60

    return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`
}

/** "25:00" / "1:05:30" — the format a running timer shows. */
export function formatClock(totalSeconds: number): string {
    const seconds = Math.max(0, Math.round(totalSeconds))
    const hours = Math.floor(seconds / 3600)
    const minutes = Math.floor((seconds % 3600) / 60)
    const rest = seconds % 60
    const pad = (value: number) => String(value).padStart(2, '0')

    return hours > 0 ? `${hours}:${pad(minutes)}:${pad(rest)}` : `${minutes}:${pad(rest)}`
}

/** Compact label for a timer button: "25 min", "1 hr 30 min", "90 sec". */
export function timerLabel(seconds: number): string {
    if (seconds < 60) return `${seconds} sec`
    if (seconds % 60 === 0) return humaniseMinutes(seconds / 60) ?? `${seconds} sec`

    return `${Math.round((seconds / 60) * 10) / 10} min`
}

export interface DetectedDuration {
    seconds: number
    /** The exact text that produced it, for highlighting in the step. */
    text: string
}

const UNIT_SECONDS: Record<string, number> = {
    second: 1,
    seconds: 1,
    sec: 1,
    secs: 1,
    minute: 60,
    minutes: 60,
    min: 60,
    mins: 60,
    hour: 3600,
    hours: 3600,
    hr: 3600,
    hrs: 3600,
}

/**
 * Find explicit cooking durations written into an instruction.
 *
 * Purely a regular expression — no model, no network call. It is deliberately
 * literal: it only matches a number (or a range) immediately followed by a
 * time word, which is how recipes actually write times, and it takes the lower
 * bound of a range so a timer never rings after the food has burned.
 *
 * Temperatures are a common false positive ("350°F for 25 minutes") and are
 * excluded by requiring a genuine time unit.
 */
export function detectDurations(text: string): DetectedDuration[] {
    const pattern =
        /(\d+(?:\.\d+)?)(?:\s*(?:-|–|—|to)\s*\d+(?:\.\d+)?)?\s*(seconds?|secs?|minutes?|mins?|hours?|hrs?)\b/gi

    const found: DetectedDuration[] = []
    const seen = new Set<number>()

    for (const match of text.matchAll(pattern)) {
        const amount = Number(match[1])
        const unit = UNIT_SECONDS[(match[2] ?? '').toLowerCase()]

        if (!unit || !Number.isFinite(amount) || amount <= 0) continue

        const seconds = Math.round(amount * unit)

        // A timer longer than 12 hours is a marinade note, not a timer.
        if (seconds < 5 || seconds > 12 * 3600 || seen.has(seconds)) continue

        seen.add(seconds)
        found.push({ seconds, text: match[0].trim() })
    }

    return found.slice(0, 3)
}
