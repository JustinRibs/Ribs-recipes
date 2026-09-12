<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Turns a wall of instruction text into discrete steps.
 *
 * Line breaks are trusted first, because a site that wrote one step per line
 * meant it. Only when the text arrives as a single paragraph does it fall back
 * to sentence splitting, and even then abbreviations common in recipes
 * ("approx.", "Dr.", "1.5 cm") are protected from becoming step boundaries.
 */
final class InstructionSplitter
{
    private const MIN_LENGTH = 3;

    /**
     * @return list<array{instruction: string, timer_seconds: null}>
     */
    public function split(string $text): array
    {
        $text = $this->tidy($text);

        if ($text === '') {
            return [];
        }

        $lines = $this->byLines($text);

        if (count($lines) < 2) {
            $lines = $this->bySentences($text);
        }

        $steps = [];

        foreach ($lines as $line) {
            $line = $this->stripLeadingNumber($line);

            if (mb_strlen($line) < self::MIN_LENGTH) {
                continue;
            }

            $steps[] = ['instruction' => mb_substr($line, 0, 5000), 'timer_seconds' => null];
        }

        return array_slice($steps, 0, 100);
    }

    /**
     * @return list<string>
     */
    private function byLines(string $text): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R+/u', $text) ?: []),
            fn (string $line): bool => $line !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function bySentences(string $text): array
    {
        // Protect decimals and known abbreviations, split, then restore.
        $guarded = preg_replace('/(\d)\.(\d)/u', '$1<DOT>$2', $text) ?? $text;

        foreach (['approx', 'Dr', 'Mr', 'Mrs', 'Ms', 'St', 'oz', 'lb', 'tsp', 'tbsp', 'min', 'hr', 'etc', 'e.g', 'i.e'] as $abbr) {
            $guarded = str_ireplace($abbr.'.', $abbr.'<DOT>', $guarded);
        }

        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/u', $guarded) ?: [];

        return array_values(array_filter(
            array_map(fn (string $part): string => trim(str_replace('<DOT>', '.', $part)), $parts),
            fn (string $part): bool => $part !== ''
        ));
    }

    private function stripLeadingNumber(string $line): string
    {
        // "1." / "Step 3:" / "3)" prefixes are redundant once steps are ordered.
        $line = preg_replace('/^(?:step\s*)?\d{1,2}\s*[.):\-–]\s*/iu', '', $line) ?? $line;

        return trim($line, " \t\n\r\0\x0B•*-–—");
    }

    private function tidy(string $text): string
    {
        $text = preg_replace('#<\s*(br|/p|/li|/div)\s*/?>#i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
