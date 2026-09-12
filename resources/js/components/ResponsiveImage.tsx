import { useState } from 'react'
import { cn } from '@/lib/cn'
import type { ImageResource } from '@/types'

interface ResponsiveImageProps {
    image: ImageResource | null
    alt?: string
    /** The `sizes` attribute — tell the browser how wide this will render. */
    sizes?: string
    className?: string
    imgClassName?: string
    /** Only the LCP image (a page hero) should ever be eager. */
    priority?: boolean
    rounded?: boolean
    /**
     * Intrinsic ratio to reserve. Pass `null` to let a CSS class own the
     * ratio instead — used where the crop changes at a breakpoint.
     */
    aspect?: string | null
}

/**
 * The single image component for the whole site.
 *
 * Local images arrive with a WebP srcset and a blurred inline placeholder;
 * remote images arrive as one URL. Both render the same way, and both reserve
 * their space from the stored dimensions so nothing on the page jumps as
 * photography loads.
 */
export function ResponsiveImage({
    image,
    alt,
    sizes = '100vw',
    className,
    imgClassName,
    priority = false,
    rounded = true,
    aspect = '4 / 3',
}: ResponsiveImageProps) {
    const [loaded, setLoaded] = useState(false)

    if (!image) {
        return (
            <div
                className={cn(
                    'flex items-center justify-center bg-surface-2 text-ink-faint',
                    rounded && 'rounded-[inherit]',
                    className,
                )}
                style={aspect === null ? undefined : { aspectRatio: aspect }}
                aria-hidden="true"
            >
                <svg
                    viewBox="0 0 48 48"
                    className="size-8 opacity-40"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.5"
                >
                    <path d="M8 34l9-11 7 8 5-6 11 9" strokeLinecap="round" strokeLinejoin="round" />
                    <rect x="6" y="10" width="36" height="28" rx="4" />
                </svg>
            </div>
        )
    }

    // Reserving the space up front is what keeps a page of photography from
    // shifting as it loads. A caller can opt out with `aspect={null}` when a
    // responsive CSS class owns the ratio instead.
    const ratio =
        aspect === null
            ? undefined
            : image.width && image.height
              ? `${image.width} / ${image.height}`
              : aspect

    return (
        <div
            className={cn('relative overflow-hidden bg-surface-2', rounded && 'rounded-[inherit]', className)}
            style={ratio === undefined ? undefined : { aspectRatio: ratio }}
        >
            {image.placeholder && !loaded && (
                <img
                    src={image.placeholder}
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 size-full scale-110 object-cover blur-xl"
                />
            )}

            <img
                src={image.src}
                srcSet={image.srcset ?? undefined}
                sizes={image.srcset ? sizes : undefined}
                alt={alt ?? image.alt ?? ''}
                width={image.width ?? undefined}
                height={image.height ?? undefined}
                loading={priority ? 'eager' : 'lazy'}
                fetchPriority={priority ? 'high' : 'auto'}
                decoding={priority ? 'sync' : 'async'}
                onLoad={() => setLoaded(true)}
                className={cn(
                    'relative size-full object-cover transition-opacity duration-500 ease-[var(--ease-out-soft)]',
                    loaded ? 'opacity-100' : 'opacity-0',
                    imgClassName,
                )}
            />
        </div>
    )
}
