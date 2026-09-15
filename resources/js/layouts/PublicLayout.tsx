import { Link, usePage } from '@inertiajs/react'
import { Search } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { CheckerRule } from '@/components/CheckerRule'
import { Logo } from '@/components/Logo'
import { SearchOverlay } from '@/components/SearchOverlay'
import { ThemeToggle } from '@/components/ThemeToggle'
import { TimerDock } from '@/components/TimerDock'
import type { SharedProps } from '@/types'

interface PublicLayoutProps {
    children: ReactNode
    /**
     * The compact category rail under the header. Suppressed on the homepage,
     * which has its own richer set of category chips — two rows of the same
     * links stacked on top of each other reads as clutter on a phone.
     */
    showCategoryRail?: boolean
}

/**
 * The public chrome: a header that stays out of the way, a footer, and the
 * timer dock. There is deliberately no admin link anywhere in it.
 */
export function PublicLayout({ children, showCategoryRail = true }: PublicLayoutProps) {
    const { site, navCategories } = usePage<SharedProps>().props
    const [searchOpen, setSearchOpen] = useState(false)
    const [scrolled, setScrolled] = useState(false)

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8)
        onScroll()
        window.addEventListener('scroll', onScroll, { passive: true })
        return () => window.removeEventListener('scroll', onScroll)
    }, [])

    // "/" focuses search, the way every search-first site behaves.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null
            const typing = target?.matches('input, textarea, select, [contenteditable]')

            if (event.key === '/' && !typing && !event.metaKey && !event.ctrlKey) {
                event.preventDefault()
                setSearchOpen(true)
            }
        }

        window.addEventListener('keydown', onKey)
        return () => window.removeEventListener('keydown', onKey)
    }, [])

    return (
        <div className="flex min-h-dvh flex-col">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-brand focus:px-5 focus:py-2.5 focus:text-ink-inverse"
            >
                Skip to content
            </a>

            <header
                className={cn(
                    'safe-top sticky top-0 z-30 transition-colors duration-200',
                    scrolled
                        ? 'border-b border-line bg-canvas/85 backdrop-blur-xl'
                        : 'border-b border-transparent bg-canvas',
                )}
            >
                <div className="shell gutter flex h-16 items-center gap-3">
                    <Logo />

                    <nav aria-label="Categories" className="ml-auto hidden items-center gap-1 lg:flex">
                        <Link
                            href="/recipes"
                            className="rounded-full px-3.5 py-2 text-[0.92rem] font-medium text-ink-muted transition hover:bg-surface-2 hover:text-ink"
                        >
                            All recipes
                        </Link>
                        {navCategories.slice(0, 5).map((category) => (
                            <Link
                                key={category.slug}
                                href={category.url}
                                className="rounded-full px-3.5 py-2 text-[0.92rem] font-medium text-ink-muted transition hover:bg-surface-2 hover:text-ink"
                            >
                                {category.name}
                            </Link>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-2 lg:ml-3">
                        <ThemeToggle className="hidden sm:inline-flex" />

                        <button
                            type="button"
                            onClick={() => setSearchOpen(true)}
                            aria-label="Search recipes"
                            className="flex h-11 items-center gap-2 rounded-full bg-surface-2 px-4 text-[0.92rem] font-medium text-ink-muted transition hover:bg-surface-3 hover:text-ink"
                        >
                            <Search className="size-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Search</span>
                        </button>
                    </div>
                </div>

                {/* Category rail on small screens, where the nav does not fit. */}
                {showCategoryRail && navCategories.length > 0 && (
                    <nav aria-label="Categories" className="lg:hidden">
                        <div className="rail rail-inset flex gap-2 overflow-x-auto pb-2.5">
                            <Link
                                href="/recipes"
                                className="flex min-h-10 shrink-0 items-center rounded-full bg-surface-2 px-4 text-[0.85rem] font-medium text-ink-muted transition active:scale-[0.97]"
                            >
                                All
                            </Link>
                            {navCategories.map((category) => (
                                <Link
                                    key={category.slug}
                                    href={category.url}
                                    className="flex min-h-10 shrink-0 items-center rounded-full bg-surface-2 px-4 text-[0.85rem] font-medium text-ink-muted transition active:scale-[0.97]"
                                >
                                    {category.name}
                                </Link>
                            ))}
                        </div>
                    </nav>
                )}
            </header>

            <main id="main" className="flex-1">
                {children}
            </main>

            <footer className="mt-20 border-t border-line bg-surface-2/40">
                <div className="shell gutter py-12">
                    <CheckerRule className="mb-9" />

                    <div className="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
                        <div className="max-w-sm">
                            <Logo />
                            <p className="mt-4 text-[0.92rem] leading-relaxed text-ink-muted">
                                {site.footerNote ||
                                    'A personal collection of recipes worth cooking again — Adriatic, Mediterranean, and whatever else earns its place.'}
                            </p>
                        </div>

                        <nav
                            aria-label="Footer"
                            className="grid grid-cols-2 gap-x-10 gap-y-2 text-[0.92rem] sm:gap-x-14"
                        >
                            <Link
                                href="/recipes"
                                className="inline-flex min-h-11 items-center text-ink-muted transition hover:text-ink"
                            >
                                All recipes
                            </Link>
                            {navCategories.slice(0, 7).map((category) => (
                                <Link
                                    key={category.slug}
                                    href={category.url}
                                    className="inline-flex min-h-11 items-center text-ink-muted transition hover:text-ink"
                                >
                                    {category.name}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    <div className="mt-10 flex flex-col-reverse items-start gap-4 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-ink-faint">
                            © {new Date().getFullYear()} {site.name}. {site.tagline}.
                        </p>
                        <ThemeToggle className="sm:hidden" />
                    </div>
                </div>
            </footer>

            <SearchOverlay open={searchOpen} onClose={() => setSearchOpen(false)} />
            <TimerDock />
        </div>
    )
}
