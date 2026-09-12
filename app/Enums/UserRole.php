<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    /** Full control, including settings and user management. */
    case Owner = 'owner';

    /** May create and edit any recipe, and manage categories/tags. */
    case Editor = 'editor';

    /** May create recipes and edit only their own. */
    case Contributor = 'contributor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Editor => 'Editor',
            self::Contributor => 'Contributor',
        };
    }

    /** Higher wins. Used for simple capability comparisons. */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 30,
            self::Editor => 20,
            self::Contributor => 10,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }
}
