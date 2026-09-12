<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Enums\UserRole;
use App\Exceptions\AccessDeniedException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Maps a verified Cloudflare identity onto an application user.
 *
 * Cloudflare decides *whether* someone may reach /admin; this decides *who*
 * they are inside the application, which is what recipes are authored as and
 * what the (future) role checks read.
 */
final class AdminUserResolver
{
    /**
     * @throws AccessDeniedException
     */
    public function resolve(AccessIdentity $identity): User
    {
        $email = strtolower(trim($identity->email));

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user !== null) {
            $this->touch($user, $identity);

            return $user;
        }

        // The configured owner is provisioned on first sign-in so a fresh
        // installation is usable without a seeder run.
        if ($this->isConfiguredOwner($email)) {
            return $this->createUser($email, $identity->name ?? (string) config('ribs.admin.name'), UserRole::Owner);
        }

        if (config('ribs.admin.auto_provision') === true) {
            return $this->createUser($email, $identity->name ?? $email, UserRole::Contributor);
        }

        Log::channel('access')->warning('Verified Access identity has no application user', [
            'email' => $email,
        ]);

        throw new AccessDeniedException(
            'Your Cloudflare sign-in worked, but this email is not set up as a Ribs Recipes author yet.'
        );
    }

    private function isConfiguredOwner(string $email): bool
    {
        $owner = strtolower(trim((string) config('ribs.admin.email')));

        return $owner !== '' && hash_equals($owner, $email);
    }

    private function createUser(string $email, string $name, UserRole $role): User
    {
        $user = User::create([
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
            'role' => $role,
            'last_seen_at' => now(),
        ]);

        Log::channel('access')->info('Provisioned admin user from Cloudflare identity', [
            'email' => $email,
            'role' => $role->value,
        ]);

        return $user;
    }

    private function touch(User $user, AccessIdentity $identity): void
    {
        $updates = [];

        // Throttled so a browse through the admin does not write on every hit.
        if ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinutes(15))) {
            $updates['last_seen_at'] = now();
        }

        if ($user->name === $user->email && filled($identity->name)) {
            $updates['name'] = $identity->name;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->saveQuietly();
        }
    }
}
