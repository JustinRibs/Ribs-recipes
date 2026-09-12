<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parsing and human-readable formatting of cooking quantities.
 *
 * Recipes are written by people, so a quantity arrives as "1 1/2", "0.5", "½",
 * "2-3" or "a pinch". Everything that can be reduced to a number is stored as
 * one (so it can be scaled), while the author's original text is preserved
 * verbatim so an unscaled recipe always reads the way it was written.
 *
 * The formatting rules here are mirrored in resources/js/lib/quantity.ts, which
 * performs the same conversion live in the browser when a visitor changes the
 * serving count. Both sides are covered by tests.
 */
final class Quantity
{
    /** Denominators cooks actually use. Ordered so simpler fractions win. */
    private const DENOMINATORS = [2, 3, 4, 8, 16];

    /** Unicode vulgar fractions that show up in pasted recipes. */
    private const VULGAR = [
        '½' => 0.5, '⅓' => 1 / 3, '⅔' => 2 / 3, '¼' => 0.25, '¾' => 0.75,
        '⅕' => 0.2, '⅖' => 0.4, '⅗' => 0.6, '⅘' => 0.8, '⅙' => 1 / 6,
        '⅚' => 5 / 6, '⅐' => 1 / 7, '⅛' => 0.125, '⅜' => 0.375, '⅝' => 0.625,
        '⅞' => 0.875, '⅑' => 1 / 9, '⅒' => 0.1,
    ];

    /**
     * Reduce a written quantity to a number, or null when it is not numeric.
     *
     * Ranges ("2-3") deliberately return null: they are kept as display text
     * and scaled end-to-end by the front end so "2-3" doubles to "4-6" rather
     * than collapsing to a single number.
     */
    public static function parse(?string $input): ?float
    {
        $normalised = self::normalise($input);

        if ($normalised === null || self::isRange($normalised)) {
            return null;
        }

        // "1 1/2" — a whole number followed by a fraction.
        if (preg_match('/^(\d+)\s+(\d+)\s*\/\s*(\d+)$/', $normalised, $m) === 1) {
            return (int) $m[3] === 0 ? null : (float) $m[1] + ((float) $m[2] / (float) $m[3]);
        }

        // "3/4"
        if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $normalised, $m) === 1) {
            return (int) $m[2] === 0 ? null : (float) $m[1] / (float) $m[2];
        }

        // "1.5" or "2"
        if (preg_match('/^\d+(?:\.\d+)?$/', $normalised) === 1) {
            return (float) $normalised;
        }

        return null;
    }

    /**
     * Format a number the way a recipe would write it: "1 1/2", not "1.5".
     */
    public static function format(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value < 0) {
            return '-'.self::format(abs($value));
        }

        // Large amounts read better rounded than fractioned.
        if ($value >= 100) {
            return (string) (int) round($value);
        }

        $whole = (int) floor($value + 1e-9);
        $remainder = $value - $whole;

        if ($remainder < 1e-6) {
            return (string) $whole;
        }

        $fraction = self::nearestFraction($remainder);

        if ($fraction === null) {
            // Not close to a friendly fraction — show a short decimal instead.
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }

        [$numerator, $denominator] = $fraction;

        // Rounding can push the remainder up to a whole unit (0.99 -> 1).
        if ($numerator === $denominator) {
            return (string) ($whole + 1);
        }

        return $whole > 0
            ? sprintf('%d %d/%d', $whole, $numerator, $denominator)
            : sprintf('%d/%d', $numerator, $denominator);
    }

    /**
     * Scale a quantity and render it, keeping unparseable text untouched.
     */
    public static function scaleDisplay(?float $quantity, ?string $display, float $factor): ?string
    {
        if ($quantity !== null) {
            return self::format($quantity * $factor);
        }

        $normalised = self::normalise($display);

        if ($normalised !== null && self::isRange($normalised)) {
            [$low, $high, $separator] = self::splitRange($normalised);

            if ($low !== null && $high !== null) {
                return self::format($low * $factor).$separator.self::format($high * $factor);
            }
        }

        return $display;
    }

    /**
     * Canonical display text for a quantity, used when importing.
     */
    public static function displayFor(?float $quantity, ?string $original): ?string
    {
        $trimmed = is_string($original) ? trim($original) : null;

        if ($trimmed !== null && $trimmed !== '') {
            return $trimmed;
        }

        return $quantity === null ? null : self::format($quantity);
    }

    /** Lower-case, collapse whitespace, expand vulgar fractions. */
    private static function normalise(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = trim($input);

        if ($value === '') {
            return null;
        }

        foreach (self::VULGAR as $glyph => $decimal) {
            if (str_contains($value, $glyph)) {
                // "1½" -> "1 1/2"; standalone "½" -> "1/2".
                $value = str_replace($glyph, ' '.self::format($decimal), $value);
            }
        }

        $value = str_replace(['⁄', ','], ['/', '.'], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private static function isRange(string $normalised): bool
    {
        return preg_match('/^\d[\d\s\/.]*\s*(?:-|–|—|to)\s*\d[\d\s\/.]*$/u', $normalised) === 1;
    }

    /** @return array{0: float|null, 1: float|null, 2: string} */
    private static function splitRange(string $normalised): array
    {
        preg_match('/^(.+?)\s*(-|–|—|to)\s*(.+)$/u', $normalised, $m);

        $separator = $m[2] === 'to' ? ' to ' : '–';

        return [self::parse($m[1] ?? null), self::parse($m[3] ?? null), $separator];
    }

    /**
     * Closest cook-friendly fraction to a value in (0, 1), or null if none is
     * close enough to be honest about.
     *
     * @return array{0:int, 1:int}|null
     */
    private static function nearestFraction(float $remainder): ?array
    {
        $best = null;
        $bestError = INF;

        foreach (self::DENOMINATORS as $denominator) {
            $numerator = (int) round($remainder * $denominator);

            if ($numerator === 0) {
                continue;
            }

            $error = abs($remainder - ($numerator / $denominator));

            if ($error < $bestError - 1e-9) {
                $bestError = $error;
                $best = [$numerator, $denominator];
            }
        }

        // 0.02 of a cup is a teaspoon of slack — beyond that, show decimals.
        return $bestError <= 0.02 ? $best : null;
    }
}
