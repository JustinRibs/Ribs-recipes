<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->role->atLeast(UserRole::Editor);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->role->atLeast(UserRole::Editor);
    }
}
