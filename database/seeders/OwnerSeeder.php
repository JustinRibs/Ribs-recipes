<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the owner account from ADMIN_EMAIL so that the first Cloudflare
 * sign-in maps onto a real user. Safe to run in production and idempotent.
 */
class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('ribs.admin.email')));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->command?->warn('ADMIN_EMAIL is not set — skipping owner creation.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => (string) config('ribs.admin.name', 'Owner'), 'role' => UserRole::Owner],
        );
    }
}
