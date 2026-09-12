<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Duration parsing for imported recipes.
 *
 * Schema.org publishes durations as ISO 8601 ("PT1H30M"), but plenty of sites
 * put "1 hr 30 mins" in the same field, so both shapes are handled here.
 */
final class Duration
{
    /** Refuse absurd values — a recipe step is not three months long. */
    private const MAX_MINUTES = 60 * 24 * 30;

    /**
     * Minutes represented by an ISO 8601 duration or a human phrase.
     */
    public static function toMinutes(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return self::clamp((int) round((float) $value));
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return self::fromIso8601($value) ?? self::fromText($value);
    }

    /**
     * Seconds represented by a human phrase — used for step timers.
     */
    public static function toSeconds(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return (int) round((float) $value);
        }

        if (! is_string($value)) {
            return null;
        }

        $iso = self::iso8601Parts($value);

        if ($iso !== null) {
            return (int) round($iso['hours'] * 3600 + $iso['minutes'] * 60 + $iso['seconds']);
        }

        $minutes = self::fromText($value);

        return $minutes === null ? null : $minutes * 60;
    }

    /** "1 h 25 min" from a minute count, for compact metadata rows. */
    public static function humanise(?int $minutes): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours.' hr' : $hours.' hr '.$rest.' min';
    }

    private static function fromIso8601(string $value): ?int
    {
        $parts = self::iso8601Parts($value);

        if ($parts === null) {
            return null;
        }

        $minutes = $parts['hours'] * 60 + $parts['minutes'] + $parts['seconds'] / 60;

        return self::clamp((int) round($minutes));
    }

    /**
     * @return array{hours: float, minutes: float, seconds: float}|null
     */
    private static function iso8601Parts(string $value): ?array
    {
        $pattern = '/^P(?:(?<d>\d+(?:\.\d+)?)D)?'
            .'(?:T(?:(?<h>\d+(?:\.\d+)?)H)?(?:(?<m>\d+(?:\.\d+)?)M)?(?:(?<s>\d+(?:\.\d+)?)S)?)?$/i';

        if (preg_match($pattern, trim($value), $m) !== 1) {
            return null;
        }

        // "P" or "PT" on their own carry no information.
        if (($m['d'] ?? '') === '' && ($m['h'] ?? '') === ''
            && ($m['m'] ?? '') === '' && ($m['s'] ?? '') === '') {
            return null;
        }

        return [
            'hours' => (float) ($m['d'] ?? 0) * 24 + (float) ($m['h'] ?? 0),
            'minutes' => (float) ($m['m'] ?? 0),
            'seconds' => (float) ($m['s'] ?? 0),
        ];
    }

    private static function fromText(string $value): ?int
    {
        $value = mb_strtolower($value);
        $minutes = 0.0;
        $matched = false;

        $units = [
            'day' => 1440,
            'hour' => 60, 'hr' => 60, 'h' => 60,
            'minute' => 1, 'min' => 1, 'm' => 1,
            'second' => 1 / 60, 'sec' => 1 / 60, 's' => 1 / 60,
        ];

        $pattern = '/(\d+(?:[.,]\d+)?)\s*(days?|hours?|hrs?|h|minutes?|mins?|m|seconds?|secs?|s)\b/u';

        if (preg_match_all($pattern, $value, $matches, PREG_SET_ORDER) === 0) {
            // A bare number in a duration field is conventionally minutes.
            return preg_match('/^\d+$/', trim($value)) === 1
                ? self::clamp((int) trim($value))
                : null;
        }

        foreach ($matches as $match) {
            $amount = (float) str_replace(',', '.', $match[1]);
            $unit = rtrim($match[2], 's');
            $unit = $unit === '' ? $match[2] : $unit;

            if (! isset($units[$unit])) {
                continue;
            }

            $minutes += $amount * $units[$unit];
            $matched = true;
        }

        return $matched ? self::clamp((int) round($minutes)) : null;
    }

    private static function clamp(int $minutes): ?int
    {
        if ($minutes <= 0 || $minutes > self::MAX_MINUTES) {
            return null;
        }

        return $minutes;
    }
}
