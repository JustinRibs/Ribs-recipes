<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Recipe;
use App\Models\User;

/**
 * Authorisation inside the admin area.
 *
 * Today there is one owner, so every check passes. The rules are written out
 * properly anyway so that adding a family contributor later is a matter of
 * creating a user row, not of retrofitting a permission model.
 */
class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recipe $recipe): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->role->atLeast(UserRole::Editor) || $recipe->user_id === $user->id;
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $this->update($user, $recipe);
    }

    public function restore(User $user, Recipe $recipe): bool
    {
        return $this->update($user, $recipe);
    }

    /** Permanent deletion is an owner-only action. */
    public function forceDelete(User $user, Recipe $recipe): bool
    {
        return $user->role === UserRole::Owner;
    }

    /** Featuring a recipe on the public homepage is an editorial decision. */
    public function feature(User $user, Recipe $recipe): bool
    {
        return $user->role->atLeast(UserRole::Editor);
    }
}
