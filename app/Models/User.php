<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 *
 * Implements Authenticatable/Authorizable so Laravel's gates and policies work
 * normally. There is deliberately no password column and no login route: the
 * only way a user is ever "signed in" is by the Cloudflare Access middleware
 * handing a verified identity to Auth::setUser().
 */
class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    /** @use HasFactory<UserFactory> */
    use AuthenticatableTrait, Authorizable, HasFactory;

    protected $fillable = ['name', 'email', 'role', 'avatar_url', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return HasMany<Recipe, $this> */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function canManageTaxonomy(): bool
    {
        return $this->role->atLeast(UserRole::Editor);
    }
}
