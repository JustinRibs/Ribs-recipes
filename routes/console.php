<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|------------------------------------------------------------------------------
| Scheduled tasks
|------------------------------------------------------------------------------
|
| One housekeeping job, and a cheap one. The scheduler runs in its own small
| container in production (see docker-compose.yml); if it is not running,
| nothing breaks — the collection just keeps a few abandoned uploads around.
|
| Deleted recipes are deliberately *not* pruned: the trash is a safety net and
| emptying it is always an explicit decision.
|
*/

// Sweep up photos uploaded into an editor that was then abandoned.
Schedule::command('media:prune')->dailyAt('03:30');
