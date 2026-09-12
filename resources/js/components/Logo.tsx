import { router } from '@inertiajs/react'
import { useCallback } from 'react'
import { cn } from '@/lib/cn'

/**
 * Taps required, and the longest gap allowed *between* consecutive taps.
 *
 * A gap threshold rather than one window around all four: a window means the
 * whole sequence has to finish inside it, which a slow phone — or a slow
 * connection — can easily miss through no fault of the person tapping. A
 * deliberate quadruple-tap has gaps of a few hundred milliseconds; coming back
 * to the logo later has gaps of seconds. 800ms separates those cleanly.
 */
const TAPS_REQUIRED = 4
const TAP_GAP_MS = 800

/**
 * Tap times live at module scope rather than in a ref.
 *
 * Each tap is also a normal visit to "/", and Inertia remounts the page
 * component on every visit — a ref would be wiped between taps and the counter
 * would never reach four. The module survives client-side navigation, so the
 * taps accumulate the way a person expects them to.
 */
let tapTimes: number[] = []

interface LogoProps {
    className?: string
    /** Suppresses the wordmark on very narrow headers. */
    compact?: boolean
    href?: string
}

/**
 * The Ribs Recipes lockup, and the way into the admin.
 *
 * Four taps in quick succession navigate to /admin. This is a convenience,
 * not a security control — Cloudflare Access is the actual boundary, and
 * /admin is just as protected whether you arrive by tapping, typing the URL or
 * guessing it. It exists only so the public site does not carry a visible
 * "Admin" link.
 *
 * A single tap still goes home, which is what the logo is for, and the whole
 * mechanism is hidden from assistive technology.
 */
export function Logo({ className, compact = false, href = '/' }: LogoProps) {
    const onClick = useCallback(
        (event: React.MouseEvent) => {
            // Ctrl/Cmd-click, middle-click and the rest belong to the browser:
            // opening the logo in a new tab should still work.
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return
            }

            event.preventDefault()

            const now = Date.now()
            const previous = tapTimes.at(-1)

            // Too long since the last one, and this is a new sequence.
            tapTimes = previous !== undefined && now - previous > TAP_GAP_MS ? [now] : [...tapTimes, now]

            if (tapTimes.length >= TAPS_REQUIRED) {
                tapTimes = []
                router.visit('/admin')

                return
            }

            // Only the first tap of a sequence goes anywhere. The rest would
            // each re-request the page you are already heading to, which made
            // the count depend on how fast the server answered.
            if (tapTimes.length !== 1) {
                return
            }

            // Already home: scroll to the top rather than re-fetching a page
            // the visitor is looking at. `html` carries scroll-behavior:
            // smooth, which the reduced-motion rule already turns off.
            if (window.location.pathname === href) {
                window.scrollTo({ top: 0 })

                return
            }

            router.visit(href)
        },
        [href],
    )

    return (
        // A plain anchor, not Inertia's <Link>: Link runs its own click handler
        // after this one and navigates whether or not the event was
        // default-prevented, so there would be no way to swallow the taps in
        // the middle of a sequence. The href is real, so right-click, middle
        // click and "open in new tab" all behave normally.
        <a
            href={href}
            onClick={onClick}
            aria-label="Ribs Recipes — home"
            // `touch-action: manipulation` removes the 300ms double-tap delay,
            // which is what makes four taps feel instant on iOS.
            className={cn(
                'group inline-flex select-none items-center gap-2.5 rounded-xl py-1 pr-2 [touch-action:manipulation]',
                'transition-opacity duration-150 hover:opacity-85',
                className,
            )}
        >
            <LogoMark className="size-9 shrink-0" />

            {!compact && (
                <span className="flex flex-col leading-none">
                    <span className="font-display text-[1.4rem] tracking-[-0.01em] text-brand">
                        Ribs Recipes
                    </span>
                    <span className="mt-1 flex items-center gap-1.5">
                        <span
                            className="checker h-[5px] w-[15px] rounded-[1px] opacity-90"
                            aria-hidden="true"
                        />
                        <span className="text-[0.58rem] font-medium uppercase tracking-[0.16em] text-ink-faint">
                            Good food goes further
                        </span>
                    </span>
                </span>
            )}
        </a>
    )
}

/**
 * The mark on its own: an olive branch over an Adriatic horizon, with the
 * Croatian checker as a small keystone. Drawn as inline SVG so it is crisp at
 * every size, themes with the page, and costs no extra request.
 */
export function LogoMark({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 40 40" className={className} role="img" aria-label="Ribs Recipes">
            <circle cx="20" cy="20" r="19" className="fill-adriatic-soft" />

            {/* Horizon */}
            <path
                d="M4 26c5.5 0 5.5 2.2 11 2.2S20.5 26 26 26s5.5 2.2 10 2.2"
                className="stroke-adriatic"
                strokeWidth="1.4"
                fill="none"
                strokeLinecap="round"
                opacity="0.55"
            />
            <path
                d="M4 30.5c5.5 0 5.5 2.2 11 2.2s5.5-2.2 11-2.2 5.5 2.2 10 2.2"
                className="stroke-adriatic"
                strokeWidth="1.2"
                fill="none"
                strokeLinecap="round"
                opacity="0.35"
            />

            {/* Bell tower and roofs */}
            <path d="M23.2 24.5V14.4l1.5-1.9 1.5 1.9v10.1z" className="fill-brand" opacity="0.9" />
            <path d="M17 24.5v-5.2l3-2.2 3 2.2v5.2z" className="fill-brand" opacity="0.72" />
            <path d="M27 24.5v-4l2.6-1.8 2.6 1.8v4z" className="fill-brand" opacity="0.6" />
            <path d="M24.7 11.1v2" className="stroke-brand" strokeWidth="0.9" strokeLinecap="round" />
            <path d="M23.9 11.9h1.6" className="stroke-brand" strokeWidth="0.9" strokeLinecap="round" />

            {/* Olive branch */}
            <path
                d="M5 12.5c4.2 1.4 7.8 3.6 10.6 6.6"
                className="stroke-olive"
                strokeWidth="1.5"
                fill="none"
                strokeLinecap="round"
            />
            <ellipse
                cx="8.4"
                cy="11.1"
                rx="3.1"
                ry="1.9"
                transform="rotate(-28 8.4 11.1)"
                className="fill-olive"
                opacity="0.9"
            />
            <ellipse
                cx="13.6"
                cy="14.6"
                rx="3"
                ry="1.8"
                transform="rotate(-14 13.6 14.6)"
                className="fill-olive"
                opacity="0.75"
            />
            <circle cx="11.2" cy="16.5" r="2.1" className="fill-olive" />

            {/* Croatian checker keystone */}
            <g className="fill-croatia">
                <rect x="17.6" y="34.2" width="2" height="2" />
                <rect x="21.6" y="34.2" width="2" height="2" />
                <rect x="19.6" y="36.2" width="2" height="2" />
            </g>
        </svg>
    )
}
