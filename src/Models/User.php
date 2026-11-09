<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use DbCms\Core\Database\Factories\UserFactory;
use DbCms\Core\Traits\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $locale
 * @property bool $is_service_account
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements OAuthenticatable
{
    /** @var array<string,mixed> */
    protected $attributes = ['is_service_account' => false];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRolesAndPermissions, Notifiable;

    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'is_service_account',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_service_account' => 'boolean',
        ];
    }
}
