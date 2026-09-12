<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ingredient scaling is the one piece of arithmetic a visitor sees directly,
 * so both directions — parsing what was written and rendering what it becomes
 * — are pinned down here. The same cases are mirrored in the TypeScript suite.
 */
class QuantityTest extends TestCase
{
    #[Test]
    #[DataProvider('parseCases')]
    public function it_parses_written_quantities(?string $input, ?float $expected): void
    {
        $result = Quantity::parse($input);

        if ($expected === null) {
            $this->assertNull($result);

            return;
        }

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta($expected, $result, 0.0001);
    }

    public static function parseCases(): array
    {
        return [
            'whole number' => ['2', 2.0],
            'decimal' => ['0.5', 0.5],
            'simple fraction' => ['3/4', 0.75],
            'mixed number' => ['1 1/2', 1.5],
            'vulgar fraction' => ['½', 0.5],
            'mixed vulgar fraction' => ['1½', 1.5],
            'comma decimal' => ['1,5', 1.5],
            'padded' => ['  2  ', 2.0],
            'range is not a single number' => ['2-3', null],
            'en dash range' => ['2–3', null],
            'word range' => ['1 to 2', null],
            'not numeric' => ['a pinch', null],
            'empty' => ['', null],
            'null' => [null, null],
            'divide by zero' => ['1/0', null],
        ];
    }

    #[Test]
    #[DataProvider('formatCases')]
    public function it_formats_numbers_the_way_recipes_are_written(float $value, string $expected): void
    {
        $this->assertSame($expected, Quantity::format($value));
    }

    public static function formatCases(): array
    {
        return [
            'whole' => [3.0, '3'],
            'half' => [0.5, '1/2'],
            'third' => [1 / 3, '1/3'],
            'two thirds' => [2 / 3, '2/3'],
            'quarter' => [0.25, '1/4'],
            'mixed half' => [1.5, '1 1/2'],
            'mixed quarter' => [2.25, '2 1/4'],
            'eighth' => [0.125, '1/8'],
            'rounds up to whole' => [0.99, '1'],
            'large numbers lose the fraction' => [120.4, '120'],
            'zero' => [0.0, '0'],
        ];
    }

    #[Test]
    public function it_scales_numeric_quantities(): void
    {
        $this->assertSame('1', Quantity::scaleDisplay(0.5, '1/2', 2));
        $this->assertSame('3/4', Quantity::scaleDisplay(1.5, '1 1/2', 0.5));
        $this->assertSame('4 1/2', Quantity::scaleDisplay(1.5, '1 1/2', 3));
    }

    #[Test]
    public function it_scales_both_ends_of_a_range(): void
    {
        $this->assertSame('4–6', Quantity::scaleDisplay(null, '2-3', 2));
        $this->assertSame('1 to 2', Quantity::scaleDisplay(null, '2 to 4', 0.5));
    }

    #[Test]
    public function it_leaves_unparseable_amounts_completely_alone(): void
    {
        // Doubling "to taste" must never produce a number.
        $this->assertSame('to taste', Quantity::scaleDisplay(null, 'to taste', 2));
        $this->assertNull(Quantity::scaleDisplay(null, null, 2));
    }

    #[Test]
    public function it_keeps_the_original_text_when_a_recipe_is_not_scaled(): void
    {
        $this->assertSame('1 1/2', Quantity::scaleDisplay(1.5, '1 1/2', 1));
    }

    #[Test]
    public function it_prefers_the_authors_own_wording_for_display(): void
    {
        $this->assertSame('1 1/2', Quantity::displayFor(1.5, '1 1/2'));
        $this->assertSame('1 1/2', Quantity::displayFor(1.5, null));
        $this->assertSame('a few', Quantity::displayFor(null, 'a few'));
        $this->assertNull(Quantity::displayFor(null, '  '));
    }
}
