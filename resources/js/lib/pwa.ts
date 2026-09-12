/**
 * Service worker registration.
 *
 * Registered after load so it never competes with the first paint, and only
 * in production — a stale worker during development is a maddening bug to
 * chase. Registration failure is non-fatal: the site works without it.
 */
export function registerServiceWorker(): void {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return
    if (!import.meta.env.PROD) return

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // Blocked, unsupported, or served without TLS — carry on.
        })
    })
}
