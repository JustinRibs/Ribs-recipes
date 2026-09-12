import { cn } from '@/lib/cn'

/**
 * A hairline rule with the Croatian checker set into the middle.
 *
 * The only place the checker pattern appears outside the logo. Used sparingly
 * — once or twice a page at most — as a brand full stop.
 */
export function CheckerRule({ className }: { className?: string }) {
    return (
        <div className={cn('flex items-center gap-4', className)} aria-hidden="true">
            <span className="h-px flex-1 bg-gradient-to-r from-transparent to-line-strong" />
            <span
                className="checker h-2 w-6 rounded-[1px] opacity-80"
                style={{ ['--checker-size' as string]: '3px' }}
            />
            <span className="h-px flex-1 bg-gradient-to-l from-transparent to-line-strong" />
        </div>
    )
}
