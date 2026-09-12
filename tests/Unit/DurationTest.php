<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Duration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DurationTest extends TestCase
{
    #[Test]
    #[DataProvider('durationCases')]
    public function it_reads_durations_from_recipe_pages(mixed $input, ?int $expected): void
    {
        $this->assertSame($expected, Duration::toMinutes($input));
    }

    public static function durationCases(): array
    {
        return [
            'iso hours and minutes' => ['PT1H30M', 90],
            'iso minutes only' => ['PT25M', 25],
            'iso with a day part' => ['P1DT2H', 1560],
            'iso seconds round to minutes' => ['PT90S', 2],
            'lowercase iso' => ['pt45m', 45],
            'empty iso carries nothing' => ['PT', null],
            'plain english' => ['1 hr 30 mins', 90],
            'plain english long form' => ['2 hours 15 minutes', 135],
            'bare number means minutes' => ['45', 45],
            'integer input' => [30, 30],
            'nonsense' => ['sometime next week', null],
            'negative is refused' => [-5, null],
            'absurd values are refused' => ['PT9000H', null],
            'null' => [null, null],
        ];
    }

    #[Test]
    public function it_converts_to_seconds_for_timers(): void
    {
        $this->assertSame(1500, Duration::toSeconds('PT25M'));
        $this->assertSame(90, Duration::toSeconds('PT1M30S'));
        $this->assertSame(1800, Duration::toSeconds('30 minutes'));
        $this->assertNull(Duration::toSeconds('later'));
    }

    #[Test]
    public function it_humanises_minute_counts(): void
    {
        $this->assertSame('25 min', Duration::humanise(25));
        $this->assertSame('1 hr', Duration::humanise(60));
        $this->assertSame('1 hr 30 min', Duration::humanise(90));
        $this->assertNull(Duration::humanise(0));
        $this->assertNull(Duration::humanise(null));
    }
}
