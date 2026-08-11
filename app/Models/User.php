<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Sign-in is by username. Email is still stored — it is what password resets
// and any future notifications will use — but it is not a credential.
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Whoever runs Lahza. Reaches the whole studio.
     */
    public const ROLE_ADMIN = 'admin';

    /**
     * Someone who signed up to build their own invitation. Reaches their own
     * account and nothing else.
     */
    public const ROLE_CUSTOMER = 'customer';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCustomer(): bool
    {
        return ! $this->isAdmin();
    }

    /** @param  Builder<User>  $query */
    public function scopeCustomers(Builder $query): void
    {
        $query->where('role', self::ROLE_CUSTOMER);
    }

    /** @param  Builder<User>  $query */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('role', self::ROLE_ADMIN);
    }

    /** The initial the avatar block shows, for a name of any script. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1));
    }
}
