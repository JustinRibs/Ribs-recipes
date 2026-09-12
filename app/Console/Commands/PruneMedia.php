<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Images\ImageStore;
use Illuminate\Console\Command;

class PruneMedia extends Command
{
    protected $signature = 'media:prune {--hours=24 : Minimum age of an unattached image}';

    protected $description = 'Delete images that were uploaded but never attached to a recipe';

    public function handle(ImageStore $images): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $deleted = $images->pruneUnattached($hours);

        $this->info("Removed {$deleted} unattached image(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
