import { Link } from '@inertiajs/react'
import { CheckerRule } from '@/components/CheckerRule'
import { Logo } from '@/components/Logo'
import { PublicLayout } from '@/layouts/PublicLayout'

interface ErrorPageProps {
    status: number
    detail?: string | null
    isAdmin?: boolean
}

const COPY: Record<number, { title: string; body: string }> = {
    403: {
        title: 'Not your kitchen',
        body: 'This part of the site is private. If you should have access, sign in through Cloudflare and try again.',
    },
    404: {
        title: 'This page is off the menu',
        body: 'The recipe or page you were after is not here. It may have been renamed, or the link may be wrong.',
    },
    419: {
        title: 'That took a moment too long',
        body: 'The page expired while it sat open. Reload it and try once more.',
    },
    429: {
        title: 'Easy now',
        body: 'Too many requests in a short window. Give it a few seconds.',
    },
    500: {
        title: 'Something boiled over',
        body: 'An unexpected error happened on the server. It has been logged, and it is not your fault.',
    },
    503: {
        title: 'Back shortly',
        body: 'The site is down for a moment of maintenance. Try again in a minute.',
    },
}

export default function ErrorPage({ status, detail, isAdmin }: ErrorPageProps) {
    const copy = COPY[status] ?? {
        title: 'Something went wrong',
        body: 'An unexpected error happened.',
    }

    const content = (
        <div className="shell gutter flex min-h-[65vh] flex-col items-center justify-center py-20 text-center">
            <p className="font-display text-[5rem] leading-none tracking-[-0.03em] text-ink-faint tabular-nums">
                {status}
            </p>

            <CheckerRule className="my-7 w-full max-w-[16rem]" />

            <h1 className="font-display text-[clamp(2rem,7vw,2.75rem)] leading-tight tracking-[-0.02em] text-ink">
                {copy.title}
            </h1>

            <p className="mt-4 max-w-md text-[1.02rem] leading-relaxed text-ink-muted text-balance">
                {detail || copy.body}
            </p>

            <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
                <Link
                    href="/"
                    className="inline-flex h-12 items-center rounded-full bg-brand px-7 font-medium text-ink-inverse transition active:scale-[0.98]"
                >
                    Back to the collection
                </Link>
                <Link
                    href="/recipes"
                    className="inline-flex h-12 items-center rounded-full bg-surface-2 px-7 font-medium text-ink transition active:scale-[0.98]"
                >
                    Browse recipes
                </Link>
            </div>
        </div>
    )

    // The admin shell needs an authenticated identity to render its navigation,
    // and a 403 is precisely the case where there isn't one — so admin errors
    // get a bare branded page instead.
    if (isAdmin) {
        return (
            <div className="flex min-h-dvh flex-col">
                <header className="gutter shell flex h-16 items-center">
                    <Logo />
                </header>
                <main className="flex-1">{content}</main>
            </div>
        )
    }

    return <PublicLayout>{content}</PublicLayout>
}
