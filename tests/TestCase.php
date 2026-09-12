<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every test starts from the taxonomy a real installation has, so
        // category/tag lookups in the importers behave as they do in practice.
        $this->withoutVite();
    }
}
