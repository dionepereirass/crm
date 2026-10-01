<?php

namespace App\Models;

use App\Models\Traits\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRolesAndPermissions;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'platform_users')
            ->withPivot('created_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Check if user has access to a specific platform.
     * SUPER_ADMIN has global access across all platforms.
     */
    public function hasAccessToPlatform(int|string|Platform $platform): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $platformId = $platform instanceof Platform ? $platform->id : (int) $platform;

        return $this->platforms->contains('id', $platformId);
    }
}
