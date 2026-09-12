<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\IngredientParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IngredientParserTest extends TestCase
{
    private IngredientParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new IngredientParser;
    }

    #[Test]
    public function it_splits_a_plain_ingredient_line(): void
    {
        $this->assertSame([
            'quantity' => 2.0,
            'quantity_display' => '2',
            'unit' => 'cups',
            'name' => 'rolled oats',
            'note' => null,
        ], $this->parser->parse('2 cups rolled oats'));
    }

    #[Test]
    public function it_understands_mixed_numbers_and_trailing_preparation(): void
    {
        $parsed = $this->parser->parse('1 1/2 pounds chicken breast, cubed');

        $this->assertSame(1.5, $parsed['quantity']);
        $this->assertSame('pounds', $parsed['unit']);
        $this->assertSame('chicken breast', $parsed['name']);
        $this->assertSame('cubed', $parsed['note']);
    }

    #[Test]
    public function it_expands_vulgar_fractions(): void
    {
        $parsed = $this->parser->parse('½ cup Greek yogurt');

        $this->assertSame(0.5, $parsed['quantity']);
        $this->assertSame('cup', $parsed['unit']);
        $this->assertSame('Greek yogurt', $parsed['name']);
    }

    #[Test]
    public function it_keeps_both_a_parenthetical_and_a_trailing_note(): void
    {
        $parsed = $this->parser->parse('1 (14 oz) can chickpeas, drained and rinsed');

        $this->assertSame(1.0, $parsed['quantity']);
        $this->assertSame('can', $parsed['unit']);
        $this->assertSame('chickpeas', $parsed['name']);
        $this->assertSame('14 oz, drained and rinsed', $parsed['note']);
    }

    #[Test]
    public function it_handles_ingredients_with_no_amount(): void
    {
        $parsed = $this->parser->parse('Salt, to taste');

        $this->assertNull($parsed['quantity']);
        $this->assertNull($parsed['unit']);
        $this->assertSame('Salt', $parsed['name']);
        $this->assertSame('to taste', $parsed['note']);
    }

    #[Test]
    public function it_keeps_ranges_as_text_so_they_can_scale_at_both_ends(): void
    {
        $parsed = $this->parser->parse('2-3 tablespoons olive oil');

        $this->assertNull($parsed['quantity']);
        $this->assertSame('2-3', $parsed['quantity_display']);
        $this->assertSame('tablespoons', $parsed['unit']);
        $this->assertSame('olive oil', $parsed['name']);
    }

    #[Test]
    public function it_does_not_invent_a_unit(): void
    {
        // "bananas" is not a unit, so it must stay part of the name.
        $parsed = $this->parser->parse('2 ripe bananas');

        $this->assertSame(2.0, $parsed['quantity']);
        $this->assertNull($parsed['unit']);
        $this->assertSame('ripe bananas', $parsed['name']);
    }

    #[Test]
    public function it_strips_list_markup_and_entities(): void
    {
        $parsed = $this->parser->parse('• 3 <strong>cloves</strong> garlic &amp; parsley');

        $this->assertSame(3.0, $parsed['quantity']);
        $this->assertSame('cloves', $parsed['unit']);
        $this->assertSame('garlic & parsley', $parsed['name']);
    }

    #[Test]
    public function it_rejects_empty_lines(): void
    {
        $this->assertNull($this->parser->parse(''));
        $this->assertNull($this->parser->parse('   '));
    }
}
