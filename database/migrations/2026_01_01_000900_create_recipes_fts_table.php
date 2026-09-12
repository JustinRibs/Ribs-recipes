<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text search index for SQLite.
 *
 * The searchable surface of a recipe spans four tables (title/description on
 * recipes, plus ingredient names, the category name and the tag names), so it
 * is denormalised into one FTS5 row per recipe and rewritten whenever a recipe
 * is saved. SQLite triggers cannot express that join, so the index is kept in
 * sync from PHP by App\Services\Recipes\RecipeSearchIndex.
 *
 * On any other driver this migration is a no-op and search transparently falls
 * back to indexed LIKE matching, which keeps a future PostgreSQL/MariaDB move
 * from being blocked on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isSqlite()) {
            return;
        }

        if (! $this->supportsFts5()) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE VIRTUAL TABLE recipes_fts USING fts5(
                recipe_id UNINDEXED,
                title,
                description,
                ingredients,
                category,
                tags,
                notes,
                tokenize = "unicode61 remove_diacritics 2",
                prefix = "2 3 4"
            )
        SQL);
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            DB::statement('DROP TABLE IF EXISTS recipes_fts');
        }
    }

    private function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    private function supportsFts5(): bool
    {
        try {
            DB::statement('CREATE VIRTUAL TABLE IF NOT EXISTS __fts5_probe USING fts5(x)');
            DB::statement('DROP TABLE IF EXISTS __fts5_probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
