<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTicket extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ticket_type_id',
        'purchase_date',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'datetime',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->valid_from !== null && $this->valid_from->isFuture()) {
            return 'inactive';
        }

        if ($this->valid_until !== null && $this->valid_until->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    public function canActivate(): bool
    {
        return ! $this->ticketType->is_long_term
            && ! $this->is_active
            && $this->status() !== 'expired';
    }
}
