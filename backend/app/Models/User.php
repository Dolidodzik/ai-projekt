<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public $timestamps = false;

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = '';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'is_admin',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_admin' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(UserTicket::class);
    }

    public function rideHistory(): HasMany
    {
        return $this->hasMany(RideHistory::class);
    }
}
