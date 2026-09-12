<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Models\RecipeImage;

/**
 * Shapes an image for the front end's <ResponsiveImage> component. Local and
 * remote images produce the same object; remote ones simply have no srcset.
 */
final class ImagePresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function make(?RecipeImage $image): ?array
    {
        if ($image === null) {
            return null;
        }

        $src = $image->displayUrl();

        if ($src === null) {
            return null;
        }

        return [
            'id' => $image->id,
            'src' => $src,
            'srcset' => $image->srcset(),
            'thumb' => $image->thumbnailUrl(),
            'width' => $image->width,
            'height' => $image->height,
            'alt' => $image->alt,
            'caption' => $image->caption,
            'placeholder' => $image->placeholder,
            'source' => $image->source->value,
            'isHero' => $image->is_hero,
        ];
    }

    /**
     * @param  iterable<int, RecipeImage>  $images
     * @return list<array<string, mixed>>
     */
    public static function collection(iterable $images): array
    {
        $out = [];

        foreach ($images as $image) {
            $presented = self::make($image);

            if ($presented !== null) {
                $out[] = $presented;
            }
        }

        return $out;
    }
}
