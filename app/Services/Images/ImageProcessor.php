<?php

declare(strict_types=1);

namespace App\Services\Images;

use App\Exceptions\FetchFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Turns arbitrary uploaded bytes into a tidy, web-ready responsive ladder.
 *
 * Everything is decoded and re-encoded, which is deliberate: it normalises the
 * format, applies the EXIF orientation so portrait iPhone photos are not
 * sideways, and drops every metadata block — including GPS coordinates, which
 * is the reason a personal recipe site should never serve camera originals
 * untouched.
 *
 * The 24 MP original is not kept. A downscaled "full" copy is stored instead,
 * because nothing on the site ever renders larger than a 2K hero.
 */
final class ImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        $driver = config('ribs.images.driver', 'gd') === 'imagick' && extension_loaded('imagick')
            ? new ImagickDriver
            : new GdDriver;

        $this->manager = new ImageManager($driver);
    }

    /**
     * @throws FetchFailedException when the bytes cannot be decoded as an image
     */
    public function process(string $binary, ?string $basename = null): ProcessedImage
    {
        try {
            $source = $this->manager->decodeBinary($binary);
        } catch (\Throwable $e) {
            throw new FetchFailedException('That file could not be read as an image.', previous: $e);
        }

        // Bake in the EXIF orientation, then flatten animations to one frame.
        $source = $source->orient();

        if ($source->count() > 1) {
            $source = $source->removeAnimation(0);
        }

        $disk = Storage::disk((string) config('ribs.images.disk'));
        $quality = (int) config('ribs.images.quality', 82);
        $maxEdge = (int) config('ribs.images.max_edge', 2400);

        $slug = $this->basenameSlug($basename);
        $directory = now()->format('Y/m').'/'.strtolower((string) Str::ulid());

        $full = $this->clone($source)->scaleDown(width: $maxEdge, height: $maxEdge);
        $width = $full->width();
        $height = $full->height();

        $variants = [];

        // WebP ladder for the srcset. Targets wider than the source are
        // dropped and the source width is always included, so the largest
        // derivative matches the image exactly and nothing is ever upscaled.
        foreach ($this->targetWidths($width) as $target) {
            $resized = $this->clone($full)->scaleDown(width: $target);
            $encoded = $resized->encodeUsingFormat(Format::WEBP, quality: $quality, strip: true);
            $path = sprintf('%s/%s-%d.webp', $directory, $slug, $resized->width());

            $disk->put($path, (string) $encoded);

            $variants[] = [
                'width' => $resized->width(),
                'height' => $resized->height(),
                'format' => 'webp',
                'path' => $path,
                'bytes' => strlen((string) $encoded),
            ];
        }

        // One JPEG at full size as the universal fallback / share image.
        $jpeg = $full->encodeUsingFormat(Format::JPEG, quality: $quality, strip: true, progressive: true);
        $jpegPath = sprintf('%s/%s-%d.jpg', $directory, $slug, $width);
        $disk->put($jpegPath, (string) $jpeg);

        $variants[] = [
            'width' => $width,
            'height' => $height,
            'format' => 'jpeg',
            'path' => $jpegPath,
            'bytes' => strlen((string) $jpeg),
        ];

        return new ProcessedImage(
            path: $jpegPath,
            variants: $variants,
            width: $width,
            height: $height,
            mime: 'image/jpeg',
            bytes: strlen((string) $jpeg),
            placeholder: $this->placeholder($full),
        );
    }

    /**
     * @return list<int>
     */
    private function targetWidths(int $sourceWidth): array
    {
        $targets = [];

        foreach ((array) config('ribs.images.widths', []) as $width) {
            if ((int) $width < $sourceWidth) {
                $targets[] = (int) $width;
            }
        }

        $targets[] = $sourceWidth;

        return array_values(array_unique($targets));
    }

    /**
     * A ~1 KB blurred data URI, inlined behind the real image so a card never
     * flashes an empty grey box on a slow connection.
     */
    private function placeholder(ImageInterface $image): ?string
    {
        try {
            $tiny = $this->clone($image)->scaleDown(width: 24, height: 24)->blur(1);
            $encoded = (string) $tiny->encodeUsingFormat(Format::JPEG, quality: 40, strip: true);

            if (strlen($encoded) > 2048) {
                return null;
            }

            return 'data:image/jpeg;base64,'.base64_encode($encoded);
        } catch (\Throwable) {
            return null;
        }
    }

    private function clone(ImageInterface $image): ImageInterface
    {
        // Intervention mutates in place; each derivative needs its own copy.
        return clone $image;
    }

    private function basenameSlug(?string $basename): string
    {
        $slug = Str::of((string) $basename)
            ->beforeLast('.')
            ->ascii()
            ->slug('-')
            ->limit(40, '')
            ->toString();

        return $slug !== '' ? $slug : 'photo';
    }
}
