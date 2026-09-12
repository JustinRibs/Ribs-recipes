<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Generates development photography locally.
 *
 * Seeded images are drawn from scratch with GD rather than downloaded, so the
 * demo collection never depends on a remote host that might vanish, and the
 * real image pipeline (orientation, resize, WebP ladder, placeholder) runs on
 * every seeded photo exactly as it would on a real upload.
 *
 * The result is a stylised overhead shot: a linen or board surface, a plate
 * with a soft shadow, a plated arrangement in the dish's own colours, and a
 * scattering of garnish. It is not a photograph, but at card size it carries
 * the same weight as one, which is the point — a homepage full of grey
 * rectangles tells you nothing about whether the design works.
 */
final class DemoImageFactory
{
    /**
     * Surface, plate and food palettes, chosen per recipe from its slug so the
     * same recipe always gets the same picture.
     *
     * @var list<array{surface: string, surfaceAlt: string, plate: string, food: list<string>, garnish: string}>
     */
    private const PALETTES = [
        // Roast / braise on dark wood
        ['surface' => '#3B2F28', 'surfaceAlt' => '#2E241E', 'plate' => '#EFE9DF',
            'food' => ['#9C4A22', '#C0682F', '#7A3418', '#D98B45'], 'garnish' => '#6F8B4E'],
        // Greens on pale linen
        ['surface' => '#E7E2D6', 'surfaceAlt' => '#D8D2C3', 'plate' => '#FBFAF7',
            'food' => ['#5F7F45', '#7FA055', '#436034', '#A8BE72'], 'garnish' => '#3F5A2C'],
        // Seafood on slate
        ['surface' => '#4A5259', 'surfaceAlt' => '#394046', 'plate' => '#F2F0EC',
            'food' => ['#E09A72', '#C9724C', '#F0BE97', '#A44F37'], 'garnish' => '#7D9B5C'],
        // Baking on warm board
        ['surface' => '#C9A882', 'surfaceAlt' => '#B5946F', 'plate' => '#FAF6EE',
            'food' => ['#C98A3E', '#E0AE63', '#A96C27', '#F0D2A0'], 'garnish' => '#8A5A2B'],
        // Tomato and paprika
        ['surface' => '#2F2A2A', 'surfaceAlt' => '#241F1F', 'plate' => '#EDE6DA',
            'food' => ['#B0332C', '#D45840', '#8A241F', '#E78B63'], 'garnish' => '#5E7F44'],
        // Cream and grain
        ['surface' => '#DAD3C4', 'surfaceAlt' => '#C7BFAD', 'plate' => '#FFFDF8',
            'food' => ['#E4D3AC', '#CDB584', '#B49763', '#F2E7CB'], 'garnish' => '#7E9257'],
        // Adriatic blue ceramic
        ['surface' => '#2C4658', 'surfaceAlt' => '#213847', 'plate' => '#E9F0F4',
            'food' => ['#D9A05B', '#B9793A', '#EFC689', '#8E5A2A'], 'garnish' => '#6B8F5B'],
        // Berry and chocolate
        ['surface' => '#33272B', 'surfaceAlt' => '#281E21', 'plate' => '#F4EEE9',
            'food' => ['#7A2A44', '#A84463', '#4E1B2C', '#C86C87'], 'garnish' => '#5F7A4A'],
    ];

    public function make(string $seed, int $width = 1600, int $height = 1100): string
    {
        $hash = crc32($seed);
        mt_srand($hash);

        $palette = self::PALETTES[$hash % count(self::PALETTES)];

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imageantialias($image, true);

        $this->paintSurface($image, $width, $height, $palette);
        $this->paintPlate($image, $width, $height, $palette);
        $this->paintFood($image, $width, $height, $palette);
        $this->paintGarnish($image, $width, $height, $palette);
        $this->paintGrain($image, $width, $height);
        $this->paintVignette($image, $width, $height);

        ob_start();
        imagejpeg($image, null, 88);
        $binary = (string) ob_get_clean();

        imagedestroy($image);
        mt_srand();

        return $binary;
    }

    /**
     * @param  array<string, mixed>  $palette
     */
    private function paintSurface(\GdImage $image, int $width, int $height, array $palette): void
    {
        [$r1, $g1, $b1] = $this->rgb($palette['surface']);
        [$r2, $g2, $b2] = $this->rgb($palette['surfaceAlt']);

        // A soft diagonal light falloff, as though from a window on the left.
        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $image,
                (int) round($r1 + ($r2 - $r1) * $t),
                (int) round($g1 + ($g2 - $g1) * $t),
                (int) round($b1 + ($b2 - $b1) * $t),
            );

            imageline($image, 0, $y, $width, $y, $color);
        }

        // Faint board grain: long, barely-there streaks.
        for ($i = 0; $i < 26; $i++) {
            $y = mt_rand(0, $height);
            $alpha = mt_rand(112, 124);
            $color = imagecolorallocatealpha($image, $r2, $g2, $b2, $alpha);
            imagefilledrectangle($image, 0, $y, $width, $y + mt_rand(2, 10), $color);
        }
    }

    /**
     * @param  array<string, mixed>  $palette
     */
    private function paintPlate(\GdImage $image, int $width, int $height, array $palette): void
    {
        $cx = (int) ($width * 0.5) + mt_rand(-40, 40);
        $cy = (int) ($height * 0.52) + mt_rand(-24, 24);
        $radius = (int) (min($width, $height) * 0.42);

        // Drop shadow. Drawn as concentric *outlines* rather than stacked
        // filled ellipses: filled ones composite on top of each other and
        // pile up into a hard black halo, while outlines each cover their
        // own band exactly once and fade out cleanly.
        $bands = 30;
        imagesetthickness($image, 4);

        for ($i = $bands; $i > 0; $i--) {
            $strength = 1 - ($i / $bands);
            $alpha = (int) round(127 - $strength * 16);
            $color = imagecolorallocatealpha($image, 0, 0, 0, max(0, min(127, $alpha)));

            imageellipse(
                $image,
                $cx + 4,
                $cy + 12,
                ($radius + $i * 3) * 2,
                ($radius + $i * 3) * 2,
                $color,
            );
        }

        imagesetthickness($image, 1);

        [$r, $g, $b] = $this->rgb($palette['plate']);

        imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, imagecolorallocate($image, $r, $g, $b));

        // Rim: a slightly darker ring inside the edge.
        $rim = imagecolorallocatealpha($image, max(0, $r - 22), max(0, $g - 22), max(0, $b - 22), 96);
        imagefilledellipse($image, $cx, $cy, (int) ($radius * 1.88), (int) ($radius * 1.88), $rim);
        imagefilledellipse($image, $cx, $cy, (int) ($radius * 1.78), (int) ($radius * 1.78), imagecolorallocate($image, $r, $g, $b));
    }

    /**
     * @param  array<string, mixed>  $palette
     */
    private function paintFood(\GdImage $image, int $width, int $height, array $palette): void
    {
        $cx = (int) ($width * 0.5);
        $cy = (int) ($height * 0.52);
        $plate = (int) (min($width, $height) * 0.42);
        $spread = (int) ($plate * 0.58);

        /** @var list<string> $foodColors */
        $foodColors = $palette['food'];

        // A loose cluster of components, largest first so smaller pieces read
        // as sitting on top rather than behind.
        $pieces = mt_rand(9, 16);

        for ($i = 0; $i < $pieces; $i++) {
            $angle = mt_rand(0, 359) * M_PI / 180;
            $distance = mt_rand(0, $spread);

            $x = (int) ($cx + cos($angle) * $distance);
            $y = (int) ($cy + sin($angle) * $distance * 0.86);

            $size = (int) ($plate * (0.34 - ($i / $pieces) * 0.2)) + mt_rand(-14, 14);
            $size = max(26, $size);

            $hex = $foodColors[array_rand($foodColors)];
            [$r, $g, $b] = $this->rgb($hex);

            // Shade each piece slightly so the pile has depth.
            $shift = mt_rand(-16, 16);
            $color = imagecolorallocate(
                $image,
                max(0, min(255, $r + $shift)),
                max(0, min(255, $g + $shift)),
                max(0, min(255, $b + $shift)),
            );

            imagefilledellipse($image, $x, $y, $size * 2, (int) ($size * 1.7), $color);

            // A soft highlight on the upper left of each piece.
            $highlight = imagecolorallocatealpha(
                $image,
                min(255, $r + 60),
                min(255, $g + 60),
                min(255, $b + 60),
                104,
            );
            imagefilledellipse(
                $image,
                (int) ($x - $size * 0.3),
                (int) ($y - $size * 0.32),
                (int) ($size * 0.9),
                (int) ($size * 0.7),
                $highlight,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $palette
     */
    private function paintGarnish(\GdImage $image, int $width, int $height, array $palette): void
    {
        $cx = (int) ($width * 0.5);
        $cy = (int) ($height * 0.52);
        $plate = (int) (min($width, $height) * 0.42);

        [$r, $g, $b] = $this->rgb($palette['garnish']);

        for ($i = 0; $i < mt_rand(7, 13); $i++) {
            $angle = mt_rand(0, 359) * M_PI / 180;
            $distance = mt_rand(0, (int) ($plate * 0.66));

            $x = (int) ($cx + cos($angle) * $distance);
            $y = (int) ($cy + sin($angle) * $distance * 0.86);
            $size = mt_rand((int) ($plate * 0.06), (int) ($plate * 0.13));

            $color = imagecolorallocatealpha($image, $r, $g, $b, mt_rand(0, 40));
            imagefilledellipse($image, $x, $y, $size * 2, (int) ($size * 0.8), $color);
        }
    }

    private function paintGrain(\GdImage $image, int $width, int $height): void
    {
        $speckles = (int) (($width * $height) / 1200);

        for ($i = 0; $i < $speckles; $i++) {
            $shade = mt_rand(0, 255);
            $color = imagecolorallocatealpha($image, $shade, $shade, $shade, 119);
            imagesetpixel($image, mt_rand(0, $width - 1), mt_rand(0, $height - 1), $color);
        }
    }

    private function paintVignette(\GdImage $image, int $width, int $height): void
    {
        $steps = 34;

        for ($i = 0; $i < $steps; $i++) {
            $alpha = 127 - (int) ((($steps - $i) / $steps) * 12);
            $color = imagecolorallocatealpha($image, 0, 0, 0, max(110, min(127, $alpha)));
            imagerectangle($image, $i, $i, $width - 1 - $i, $height - 1 - $i, $color);
        }
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
