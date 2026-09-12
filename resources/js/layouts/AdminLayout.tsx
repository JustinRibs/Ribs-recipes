import { Link, router, usePage } from '@inertiajs/react'
import {
    CheckCircle2,
    ChevronLeft,
    FolderTree,
    LayoutDashboard,
    Menu,
    Settings,
    Tags,
    Upload,
    UtensilsCrossed,
    X,
    XCircle,
} from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { LogoMark } from '@/components/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { adminRoutes } from '@/lib/routes'
import type { SharedProps } from '@/types'

const NAV = [
    { href: adminRoutes.dashboard, label: 'Dashboard', Icon: LayoutDashboard, exact: true },
    { href: adminRoutes.recipes, label: 'Recipes', Icon: UtensilsCrossed },
    { href: adminRoutes.categories, label: 'Categories', Icon: FolderTree },
    { href: adminRoutes.tags, label: 'Tags', Icon: Tags },
    { href: adminRoutes.importUrl, label: 'Import', Icon: Upload, match: '/admin/import' },
    { href: adminRoutes.settings, label: 'Settings', Icon: Settings },
]

interface AdminLayoutProps {
    children: ReactNode
    title: string
    description?: string
    actions?: ReactNode
    /** Renders a back link instead of the page title block. */
    backTo?: { href: string; label: string }
}

/**
 * The admin shell.
 *
 * More functional than the public site, but built from the same tokens and
 * type, so moving between them does not feel like two different products. A
 * permanent sidebar from `lg` up, a slide-over below it.
 */
export function AdminLayout({ children, title, description, actions, backTo }: AdminLayoutProps) {
    const { url, props } = usePage<SharedProps>()
    const { auth, flash } = props
    const [navOpen, setNavOpen] = useState(false)

    // Inertia's navigation event is an external system to subscribe to, which
    // is both the correct shape for an effect and more precise than watching
    // the URL: the drawer closes the moment a visit starts.
    useEffect(() => router.on('start', () => setNavOpen(false)), [])

    return (
        <div className="min-h-dvh bg-canvas lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
            {/* --- Sidebar (desktop) --------------------------------------- */}
            <aside className="hidden border-r border-line bg-surface-2/40 lg:flex lg:h-dvh lg:flex-col lg:sticky lg:top-0">
                <SidebarContent auth={auth} url={url} />
            </aside>

            {/* --- Slide-over (mobile) ------------------------------------- */}
            {navOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <button
                        type="button"
                        aria-label="Close menu"
                        onClick={() => setNavOpen(false)}
                        className="absolute inset-0 bg-black/45 backdrop-blur-[2px]"
                    />
                    <div className="absolute inset-y-0 left-0 flex w-[17rem] flex-col bg-surface shadow-[var(--shadow-overlay)]">
                        <SidebarContent auth={auth} url={url} onClose={() => setNavOpen(false)} />
                    </div>
                </div>
            )}

            <div className="flex min-w-0 flex-col">
                {/* --- Top bar --------------------------------------------- */}
                <header className="safe-top sticky top-0 z-30 border-b border-line bg-canvas/88 backdrop-blur-xl">
                    <div className="flex h-16 items-center gap-3 px-4 sm:px-6">
                        <button
                            type="button"
                            onClick={() => setNavOpen(true)}
                            aria-label="Open menu"
                            className="flex size-11 shrink-0 items-center justify-center rounded-full text-ink transition hover:bg-surface-2 lg:hidden"
                        >
                            <Menu className="size-5" aria-hidden="true" />
                        </button>

                        <div className="min-w-0 flex-1">
                            {backTo ? (
                                <Link
                                    href={backTo.href}
                                    className="-my-2 inline-flex min-h-11 items-center gap-1.5 text-[0.92rem] font-medium text-ink-muted transition hover:text-ink"
                                >
                                    <ChevronLeft className="size-4" aria-hidden="true" />
                                    {backTo.label}
                                </Link>
                            ) : (
                                <h1 className="truncate font-display text-[1.5rem] leading-tight tracking-tight text-ink">
                                    {title}
                                </h1>
                            )}
                        </div>

                        {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                    </div>
                </header>

                <main className="min-w-0 flex-1 px-4 pb-24 pt-6 sm:px-6 lg:pb-16">
                    {backTo && (
                        <div className="mb-6">
                            <h1 className="font-display text-[clamp(1.85rem,5vw,2.4rem)] leading-tight tracking-[-0.02em] text-ink">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-1.5 text-[0.98rem] text-ink-muted">{description}</p>
                            )}
                        </div>
                    )}
                    {!backTo && description && (
                        <p className="-mt-2 mb-6 text-[0.98rem] text-ink-muted">{description}</p>
                    )}

                    {children}
                </main>
            </div>

            {/* Keyed on the message so a new one gets a fresh component —
                and its own dismiss timer — instead of an effect resetting it. */}
            <FlashMessages key={flash.success ?? flash.error ?? 'none'} flash={flash} />
        </div>
    )
}

function SidebarContent({
    auth,
    url,
    onClose,
}: {
    auth: SharedProps['auth']
    url: string
    onClose?: () => void
}) {
    return (
        <>
            <div className="safe-top flex h-16 items-center gap-2.5 px-5">
                <LogoMark className="size-8 shrink-0" />
                <span className="font-display text-[1.15rem] tracking-tight text-brand">Ribs Recipes</span>
                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close menu"
                        className="ml-auto flex size-10 items-center justify-center rounded-full text-ink-muted transition hover:bg-surface-2"
                    >
                        <X className="size-5" aria-hidden="true" />
                    </button>
                )}
            </div>

            <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 py-4" aria-label="Admin">
                {NAV.map(({ href, label, Icon, exact, match }) => {
                    const path = url.split('?')[0] ?? ''
                    const active = exact ? path === href : path.startsWith(match ?? href)

                    return (
                        <Link
                            key={href}
                            href={href}
                            className={cn(
                                'flex h-11 items-center gap-3 rounded-xl px-3 text-[0.95rem] font-medium transition',
                                active
                                    ? 'bg-surface text-ink shadow-[var(--shadow-card)]'
                                    : 'text-ink-muted hover:bg-surface hover:text-ink',
                            )}
                            aria-current={active ? 'page' : undefined}
                        >
                            <Icon className="size-4.5 shrink-0" aria-hidden="true" />
                            {label}
                        </Link>
                    )
                })}
            </nav>

            <div className="safe-bottom space-y-3 border-t border-line px-4 py-4">
                <ThemeToggle />

                {auth && (
                    <div className="min-w-0">
                        <p className="truncate text-[0.88rem] font-medium text-ink">{auth.name}</p>
                        <p className="truncate text-[0.8rem] text-ink-faint">{auth.email}</p>
                    </div>
                )}

                <Link
                    href="/"
                    className="block text-[0.85rem] font-medium text-adriatic underline underline-offset-4"
                >
                    View the public site
                </Link>
            </div>
        </>
    )
}

/**
 * Flash messages. Announced politely, dismissable, and auto-cleared — a toast
 * that lingers is a toast that covers the button you need next.
 */
function FlashMessages({ flash }: { flash: SharedProps['flash'] }) {
    const [dismissed, setDismissed] = useState(false)
    const message = flash.success ?? flash.error
    const isError = Boolean(flash.error)

    useEffect(() => {
        // Errors stay until they are dismissed; a success message that lingers
        // is a message covering the button you need next.
        if (!message || isError) return

        const timeout = window.setTimeout(() => setDismissed(true), 4200)

        return () => window.clearTimeout(timeout)
    }, [message, isError])

    if (!message || dismissed) return null

    return (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
        >
            <div
                className={cn(
                    'pointer-events-auto flex max-w-md items-center gap-3 rounded-2xl px-4 py-3 shadow-[var(--shadow-overlay)] ring-1 backdrop-blur-xl',
                    isError
                        ? 'bg-croatia text-white ring-croatia'
                        : 'bg-surface/94 text-ink ring-line-strong',
                )}
            >
                {isError ? (
                    <XCircle className="size-5 shrink-0" aria-hidden="true" />
                ) : (
                    <CheckCircle2 className="size-5 shrink-0 text-olive" aria-hidden="true" />
                )}

                <p className="text-[0.92rem] font-medium">{message}</p>

                <button
                    type="button"
                    onClick={() => setDismissed(true)}
                    aria-label="Dismiss"
                    className="ml-1 flex size-8 shrink-0 items-center justify-center rounded-full transition hover:bg-black/10"
                >
                    <X className="size-4" aria-hidden="true" />
                </button>
            </div>
        </div>
    )
}

/** Reload the current page's props — used after a non-Inertia mutation. */
export function refreshAdmin(): void {
    router.reload({ only: [] })
}
