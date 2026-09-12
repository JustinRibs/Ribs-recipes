<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Duration;
use App\Support\Quantity;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Parses the Ribs Recipes CSV format into reviewable rows.
 *
 * Every row is validated independently and carries its own errors and
 * warnings, so one malformed recipe never blocks the other forty — it is
 * simply marked un-importable in the preview.
 *
 * Column reference is in README.md and in the downloadable template.
 */
final class CsvRecipeParser
{
    /** Recognised columns. Unknown columns are ignored, not fatal. */
    public const COLUMNS = [
        'title', 'description', 'category', 'tags', 'prep_minutes', 'cook_minutes',
        'servings', 'calories', 'source_url', 'source_name', 'hero_image_url',
        'ingredients', 'instructions', 'notes', 'favorite',
    ];

    public function __construct(private readonly IngredientParser $ingredients) {}

    /**
     * @return array{rows: list<array<string, mixed>>, headers: list<string>, errors: list<string>}
     */
    public function parse(string $path): array
    {
        try {
            $reader = Reader::createFromPath($path, 'r');
            $reader->setHeaderOffset(0);
            // Blank spacer rows are common in hand-edited spreadsheets.
            $reader->skipEmptyRecords();
            $headers = array_map($this->normaliseHeader(...), $reader->getHeader());
        } catch (\Throwable $e) {
            return [
                'rows' => [],
                'headers' => [],
                'errors' => ['That file could not be read as a CSV: '.$e->getMessage()],
            ];
        }

        if (! in_array('title', $headers, true)) {
            return [
                'rows' => [],
                'headers' => $headers,
                'errors' => ['The file has no "title" column. Download the template to see the expected columns.'],
            ];
        }

        $max = (int) config('ribs.csv.max_rows', 250);
        $rows = [];
        $index = 0;
        $truncated = false;

        foreach (Statement::create()->process($reader) as $record) {
            if ($index >= $max) {
                $truncated = true;
                break;
            }

            $rows[] = $this->parseRow($this->normaliseRecord($record), $index + 2, $index);
            $index++;
        }

        return [
            'rows' => $rows,
            'headers' => $headers,
            'errors' => $truncated
                ? ["Only the first {$max} rows were read. Split larger files and import them in batches."]
                : [],
        ];
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, mixed>
     */
    private function parseRow(array $record, int $lineNumber, int $index): array
    {
        $errors = [];
        $warnings = [];

        $title = trim($record['title'] ?? '');

        if ($title === '') {
            $errors[] = 'Missing a title.';
        } elseif (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
            $warnings[] = 'The title was longer than 200 characters and has been trimmed.';
        }

        $ingredients = $this->parseIngredients($record['ingredients'] ?? '', $warnings);
        $steps = $this->parseInstructions($record['instructions'] ?? '', $warnings);

        if ($ingredients === []) {
            $warnings[] = 'No ingredients — this will be imported as a draft.';
        }

        if ($steps === []) {
            $warnings[] = 'No instructions — this will be imported as a draft.';
        }

        $sourceUrl = trim($record['source_url'] ?? '');

        if ($sourceUrl !== '' && ! $this->looksLikeUrl($sourceUrl)) {
            $warnings[] = 'The source URL does not look like a web address and has been dropped.';
            $sourceUrl = '';
        }

        $heroImageUrl = trim($record['hero_image_url'] ?? '');

        if ($heroImageUrl !== '' && ! $this->looksLikeUrl($heroImageUrl)) {
            $warnings[] = 'The hero image URL does not look like a web address and has been dropped.';
            $heroImageUrl = '';
        }

        $prep = $this->minutes($record['prep_minutes'] ?? '', 'prep_minutes', $warnings);
        $cook = $this->minutes($record['cook_minutes'] ?? '', 'cook_minutes', $warnings);

        return [
            'index' => $index,
            'line' => $lineNumber,
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'title' => $title,
            'category' => trim($record['category'] ?? '') ?: null,
            'tags' => $this->splitTags($record['tags'] ?? ''),
            'ingredientCount' => count($ingredients),
            'stepCount' => count($steps),
            'heroImageUrl' => $heroImageUrl ?: null,
            'recipe' => [
                'title' => $title,
                'description' => Str::limit(trim($record['description'] ?? ''), 500, ''),
                'notes' => trim($record['notes'] ?? ''),
                'prep_minutes' => $prep,
                'cook_minutes' => $cook,
                'servings' => $this->positiveInt($record['servings'] ?? '', 999),
                'calories' => $this->positiveInt($record['calories'] ?? '', 20000),
                'source_url' => $sourceUrl ?: null,
                'source_name' => trim($record['source_name'] ?? '') ?: $this->hostOf($sourceUrl),
                'is_favorite' => $this->boolean($record['favorite'] ?? ''),
                'ingredients' => $ingredients,
                'steps' => $steps,
            ],
        ];
    }

    /**
     * `quantity | unit | ingredient | note`, one per line. Lines without pipes
     * fall back to the free-text ingredient parser so a half-formatted file
     * still imports sensibly.
     *
     * @param  list<string>  $warnings
     * @return list<array<string, mixed>>
     */
    private function parseIngredients(string $cell, array &$warnings): array
    {
        $rows = [];
        $malformed = 0;

        foreach ($this->lines($cell) as $line) {
            if (! str_contains($line, '|')) {
                $parsed = $this->ingredients->parse($line);

                if ($parsed !== null) {
                    $rows[] = $parsed;
                } else {
                    $malformed++;
                }

                continue;
            }

            $parts = array_map(trim(...), explode('|', $line));
            $quantityText = $parts[0] ?? '';
            $unit = $parts[1] ?? '';
            $name = $parts[2] ?? '';
            $note = $parts[3] ?? '';

            if ($name === '') {
                $malformed++;

                continue;
            }

            $quantity = Quantity::parse($quantityText);

            $rows[] = [
                'quantity' => $quantity,
                'quantity_display' => $quantityText !== '' ? $quantityText : null,
                'unit' => $unit !== '' ? mb_substr($unit, 0, 40) : null,
                'name' => mb_substr($name, 0, 200),
                'note' => $note !== '' ? mb_substr($note, 0, 200) : null,
            ];
        }

        if ($malformed > 0) {
            $warnings[] = "{$malformed} ingredient line(s) had no ingredient name and were skipped.";
        }

        return array_slice($rows, 0, 200);
    }

    /**
     * One step per line. An optional trailing `| 25` sets a timer in minutes.
     *
     * @param  list<string>  $warnings
     * @return list<array<string, mixed>>
     */
    private function parseInstructions(string $cell, array &$warnings): array
    {
        $steps = [];

        foreach ($this->lines($cell) as $line) {
            $timerSeconds = null;

            if (str_contains($line, '|')) {
                $parts = array_map(trim(...), explode('|', $line, 2));
                $candidate = Duration::toSeconds($parts[1] ?? '');

                if ($candidate !== null && $candidate > 0) {
                    $line = $parts[0];
                    $timerSeconds = min($candidate, 86400);
                }
            }

            $line = trim(preg_replace('/^(?:step\s*)?\d{1,2}\s*[.):\-]\s*/iu', '', $line) ?? $line);

            if ($line === '') {
                continue;
            }

            $steps[] = [
                'instruction' => mb_substr($line, 0, 5000),
                'timer_seconds' => $timerSeconds,
            ];
        }

        if (count($steps) > 100) {
            $warnings[] = 'Only the first 100 steps were kept.';
        }

        return array_slice($steps, 0, 100);
    }

    /**
     * @return list<string>
     */
    private function lines(string $cell): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R/u', $cell) ?: []),
            fn (string $line): bool => $line !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function splitTags(string $cell): array
    {
        // Pipes are the documented separator; commas and semicolons are
        // accepted because spreadsheets tempt people into using them.
        $parts = preg_split('/[|;,]/u', $cell) ?: [];

        return array_values(array_slice(array_unique(array_filter(
            array_map(fn (string $tag): string => trim($tag), $parts)
        )), 0, 20));
    }

    /**
     * @param  list<string>  $warnings
     */
    private function minutes(string $value, string $column, array &$warnings): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $minutes = Duration::toMinutes($value);

        if ($minutes === null) {
            $warnings[] = "Could not read “{$value}” as a time for {$column}.";

            return null;
        }

        return min($minutes, 10080);
    }

    private function positiveInt(string $value, int $max): ?int
    {
        $value = trim($value);

        if ($value === '' || preg_match('/(\d+)/', $value, $m) !== 1) {
            return null;
        }

        return min(max((int) $m[1], 0), $max) ?: null;
    }

    private function boolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'x', 'favorite'], true);
    }

    private function looksLikeUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $value) === 1;
    }

    private function hostOf(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? Str::of($host)->after('www.')->toString() : null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string>
     */
    private function normaliseRecord(array $record): array
    {
        $out = [];

        foreach ($record as $key => $value) {
            $out[$this->normaliseHeader((string) $key)] = is_string($value) ? $value : (string) $value;
        }

        return $out;
    }

    private function normaliseHeader(string $header): string
    {
        // Tolerate "Prep Minutes", "prep-minutes", stray BOM and whitespace.
        $header = preg_replace('/^\x{FEFF}/u', '', trim($header)) ?? $header;

        return Str::of($header)->lower()->replace([' ', '-'], '_')->toString();
    }
}
