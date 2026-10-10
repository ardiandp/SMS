<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
            'last_login_at' => 'datetime',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Whether the account is allowed to authenticate.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * @return HasMany<SchoolUserMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolUserMembership::class);
    }

    /**
     * Schools this user may currently access (active membership only).
     *
     * @return Builder<School>
     */
    public function accessibleSchools()
    {
        return School::whereIn('id', function ($query): void {
            $query->select('school_id')
                ->from('school_user_memberships')
                ->where('user_id', $this->getKey())
                ->where('status', 'active');
        });
    }
}
