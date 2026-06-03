<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    public $timestamps = false;

    public const VALIDITY_60_MIN = 60;

    public const VALIDITY_WEEKLY = 10_080;

    public const VALIDITY_MONTHLY = 43_200;

    public const VALIDITY_SEMESTER = 259_200;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'validity_minutes',
        'is_long_term',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'validity_minutes' => 'integer',
            'is_long_term' => 'boolean',
        ];
    }

    public function userTickets(): HasMany
    {
        return $this->hasMany(UserTicket::class);
    }

    public static function allowedValidityMinutes(): array
    {
        return [
            self::VALIDITY_60_MIN,
            self::VALIDITY_WEEKLY,
            self::VALIDITY_MONTHLY,
            self::VALIDITY_SEMESTER,
        ];
    }
}
