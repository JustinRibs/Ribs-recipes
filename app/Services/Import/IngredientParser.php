<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Quantity;

/**
 * Splits a written ingredient line into structured parts.
 *
 * "1 1/2 pounds chicken breast, cubed" becomes
 * quantity 1.5, unit "pounds", name "chicken breast", note "cubed".
 *
 * The parser is deliberately conservative. When it cannot confidently identify
 * a unit it leaves the text in the name, because a wrong unit is far more
 * damaging in a recipe than a missing one — and the import preview exists
 * precisely so a human can fix whatever it got wrong.
 */
final class IngredientParser
{
    /**
     * Measurement words, longest-first so "fluid ounces" wins over "ounces".
     *
     * @var list<string>
     */
    private const UNITS = [
        'fluid ounces', 'fluid ounce', 'fl oz', 'fl. oz.',
        'tablespoons', 'tablespoon', 'tbsps', 'tbsp', 'tbs', 'tbl',
        'teaspoons', 'teaspoon', 'tsps', 'tsp',
        'kilograms', 'kilogram', 'kgs', 'kg',
        'grams', 'gram', 'gr', 'g',
        'milliliters', 'milliliter', 'millilitres', 'millilitre', 'mls', 'ml',
        'liters', 'liter', 'litres', 'litre', 'l',
        'ounces', 'ounce', 'ozs', 'oz',
        'pounds', 'pound', 'lbs', 'lb',
        'cups', 'cup', 'c',
        'quarts', 'quart', 'qts', 'qt',
        'pints', 'pint', 'pts', 'pt',
        'gallons', 'gallon', 'gal',
        'cloves', 'clove', 'heads', 'head', 'bunches', 'bunch',
        'sprigs', 'sprig', 'stalks', 'stalk', 'stems', 'stem',
        'slices', 'slice', 'sticks', 'stick', 'strips', 'strip',
        'cans', 'can', 'jars', 'jar', 'packages', 'package', 'packets', 'packet',
        'containers', 'container', 'bottles', 'bottle', 'boxes', 'box', 'bags', 'bag',
        'pinches', 'pinch', 'dashes', 'dash', 'handfuls', 'handful',
        'scoops', 'scoop', 'squares', 'square', 'sheets', 'sheet',
        'pieces', 'piece', 'ears', 'ear', 'fillets', 'fillet', 'rashers', 'rasher',
        'dl', 'cl', 'dcl', 'žlica', 'žlice', 'šalica', 'šalice',
    ];

    /**
     * Trailing phrases that describe preparation rather than the ingredient.
     *
     * @var list<string>
     */
    private const NOTE_MARKERS = [
        'to taste', 'to serve', 'for serving', 'for garnish', 'to garnish',
        'for dusting', 'for greasing', 'for frying', 'optional', 'or to taste',
        'divided', 'plus more', 'plus extra', 'room temperature', 'at room temperature',
        'chopped', 'finely chopped', 'roughly chopped', 'diced', 'finely diced',
        'minced', 'finely minced', 'sliced', 'thinly sliced', 'grated', 'finely grated',
        'shredded', 'crushed', 'peeled', 'peeled and chopped', 'peeled and diced',
        'melted', 'softened', 'beaten', 'lightly beaten', 'drained', 'drained and rinsed',
        'rinsed', 'trimmed', 'halved', 'quartered', 'cubed', 'julienned', 'zested',
        'juiced', 'toasted', 'cooked', 'uncooked', 'frozen', 'thawed', 'packed',
        'plus more for serving', 'well shaken', 'stemmed', 'seeded', 'deseeded',
    ];

    /**
     * @return array{quantity: float|null, quantity_display: string|null, unit: string|null, name: string, note: string|null}|null
     */
    public function parse(string $line): ?array
    {
        $line = $this->tidy($line);

        if ($line === '') {
            return null;
        }

        $note = null;

        // Parentheticals are almost always a note: "(about 2 medium)".
        if (preg_match('/^(.*?)\s*\(([^)]{2,80})\)\s*(.*)$/u', $line, $m) === 1) {
            $rest = trim($m[1].' '.$m[3]);

            if ($rest !== '') {
                $note = trim($m[2]);
                $line = $rest;
            }
        }

        [$line, $trailingNote] = $this->splitTrailingNote($line);

        // A line can carry both — "1 (14 oz) can chickpeas, drained" — and
        // losing either half would quietly change the recipe.
        $note = implode(', ', array_filter([$note, $trailingNote])) ?: null;

        [$quantityText, $line] = $this->takeQuantity($line);
        [$unit, $line] = $this->takeUnit($line);

        $name = trim($line, " \t\n\r\0\x0B,.;:-–—");

        if ($name === '') {
            // Nothing but a number and a unit — not a usable ingredient, but
            // the original text is worth keeping as the name.
            $name = trim(implode(' ', array_filter([$quantityText, $unit])));
            $quantityText = null;
            $unit = null;
        }

        if ($name === '') {
            return null;
        }

        $quantity = Quantity::parse($quantityText);

        return [
            'quantity' => $quantity,
            'quantity_display' => $quantityText !== null && $quantityText !== '' ? $quantityText : null,
            'unit' => $unit,
            'name' => mb_substr($name, 0, 200),
            'note' => $note === null ? null : mb_substr($note, 0, 200),
        ];
    }

    private function tidy(string $line): string
    {
        $line = strip_tags($line);
        $line = html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Non-breaking spaces are endemic in scraped markup.
        $line = str_replace(["\u{00A0}", "\u{2009}", "\u{202F}"], ' ', $line);
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return trim($line, " \t\n\r\0\x0B•-–—*");
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitTrailingNote(string $line): array
    {
        if (! str_contains($line, ',')) {
            return [$line, null];
        }

        $position = mb_strrpos($line, ',');
        $head = trim(mb_substr($line, 0, $position));
        $tail = trim(mb_substr($line, $position + 1));

        if ($head === '' || $tail === '') {
            return [$line, null];
        }

        $candidate = mb_strtolower(rtrim($tail, '.'));

        foreach (self::NOTE_MARKERS as $marker) {
            if ($candidate === $marker || str_starts_with($candidate, $marker.' ') || str_ends_with($candidate, ' '.$marker)) {
                return [$head, $tail];
            }
        }

        return [$line, null];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function takeQuantity(string $line): array
    {
        // Expand "1½" / "½" before matching so the regex only sees ASCII.
        $line = $this->expandVulgarFractions($line);

        $number = '\d+(?:[.,]\d+)?';
        $mixed = "(?:{$number}\\s+\\d+\\s*\\/\\s*\\d+|\\d+\\s*\\/\\s*\\d+|{$number})";
        $pattern = "/^({$mixed}(?:\\s*(?:-|–|—|to)\\s*{$mixed})?)\\s*(.*)$/u";

        if (preg_match($pattern, $line, $m) !== 1) {
            return [null, $line];
        }

        return [trim(str_replace(',', '.', $m[1])), trim($m[2])];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function takeUnit(string $line): array
    {
        foreach (self::UNITS as $unit) {
            $quoted = preg_quote($unit, '/');

            // A unit must be a whole word and must be followed by something —
            // "2 cups" alone leaves "cups" as the name, which is right.
            if (preg_match("/^{$quoted}\\b\\.?\\s+(.+)$/iu", $line, $m) === 1) {
                return [$unit, trim($m[1])];
            }
        }

        return [null, $line];
    }

    private function expandVulgarFractions(string $line): string
    {
        $map = [
            '½' => ' 1/2', '⅓' => ' 1/3', '⅔' => ' 2/3', '¼' => ' 1/4', '¾' => ' 3/4',
            '⅕' => ' 1/5', '⅖' => ' 2/5', '⅗' => ' 3/5', '⅘' => ' 4/5',
            '⅙' => ' 1/6', '⅚' => ' 5/6', '⅐' => ' 1/7', '⅛' => ' 1/8',
            '⅜' => ' 3/8', '⅝' => ' 5/8', '⅞' => ' 7/8', '⅑' => ' 1/9', '⅒' => ' 1/10',
        ];

        $expanded = strtr($line, $map);
        $expanded = str_replace('⁄', '/', $expanded);

        return trim(preg_replace('/\s+/u', ' ', $expanded) ?? $expanded);
    }
}
