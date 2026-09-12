<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Recipes\RecipeSearchIndex;
use Illuminate\Console\Command;

class ReindexRecipes extends Command
{
    protected $signature = 'recipes:reindex';

    protected $description = 'Rebuild the full-text search index for every recipe';

    public function handle(RecipeSearchIndex $index): int
    {
        if (! $index->available()) {
            $this->warn('Full-text search is not available on this connection; nothing to do.');

            return self::SUCCESS;
        }

        $count = $index->rebuild();

        $this->info("Reindexed {$count} recipes.");

        return self::SUCCESS;
    }
}
