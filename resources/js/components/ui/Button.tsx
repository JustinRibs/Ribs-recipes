import { Link } from '@inertiajs/react'
import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { cn } from '@/lib/cn'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'quiet'
type Size = 'sm' | 'md' | 'lg'

const base =
    'inline-flex items-center justify-center gap-2 rounded-full font-medium transition ' +
    'duration-150 ease-[var(--ease-out-soft)] select-none ' +
    'disabled:pointer-events-none disabled:opacity-45 active:scale-[0.98] ' +
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-adriatic'

const variants: Record<Variant, string> = {
    primary: 'bg-brand text-ink-inverse hover:bg-brand-soft shadow-[var(--shadow-card)]',
    secondary: 'bg-surface text-ink ring-1 ring-line-strong hover:bg-surface-2 shadow-[var(--shadow-card)]',
    ghost: 'text-ink hover:bg-surface-2',
    quiet: 'bg-surface-2 text-ink hover:bg-surface-3',
    danger: 'bg-croatia text-white hover:brightness-110',
}

const sizes: Record<Size, string> = {
    // 44px is the comfortable one-handed target and the default here. `sm` is
    // for dense header rows and never drops below 40px, which is the floor
    // this design holds itself to.
    sm: 'h-10 px-4 text-sm',
    md: 'h-11 px-5 text-[0.95rem]',
    lg: 'h-13 px-7 text-base',
}

interface CommonProps {
    variant?: Variant
    size?: Size
    className?: string
    children?: ReactNode
}

export const Button = forwardRef<HTMLButtonElement, CommonProps & ButtonHTMLAttributes<HTMLButtonElement>>(
    function Button(
        { variant = 'primary', size = 'md', className, children, type = 'button', ...props },
        ref,
    ) {
        return (
            <button
                ref={ref}
                type={type}
                className={cn(base, variants[variant], sizes[size], className)}
                {...props}
            >
                {children}
            </button>
        )
    },
)

interface ButtonLinkProps extends CommonProps {
    href: string
    external?: boolean
    prefetch?: boolean
    /**
     * Required whenever the visible label is hidden at small widths — without
     * it the control announces as an unnamed button on exactly the devices
     * this site is built for.
     */
    'aria-label'?: string
}

export function ButtonLink({
    href,
    external,
    variant = 'primary',
    size = 'md',
    className,
    children,
    prefetch,
    ...props
}: ButtonLinkProps) {
    const classes = cn(base, variants[variant], sizes[size], className)

    if (external) {
        return (
            <a href={href} className={classes} target="_blank" rel="noreferrer noopener" {...props}>
                {children}
            </a>
        )
    }

    return (
        <Link href={href} className={classes} prefetch={prefetch} {...props}>
            {children}
        </Link>
    )
}

/** A square icon-only button, sized for thumbs. */
export const IconButton = forwardRef<
    HTMLButtonElement,
    CommonProps & ButtonHTMLAttributes<HTMLButtonElement> & { label: string }
>(function IconButton({ variant = 'ghost', className, children, label, type = 'button', ...props }, ref) {
    return (
        <button
            ref={ref}
            type={type}
            aria-label={label}
            title={label}
            className={cn(base, variants[variant], 'size-11 shrink-0 p-0', className)}
            {...props}
        >
            {children}
        </button>
    )
})
