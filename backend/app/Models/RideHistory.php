<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideHistory extends Model
{
    protected $table = 'ride_history';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'trip_id',
        'from_stop_id',
        'to_stop_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(GtfsTrip::class, 'trip_id');
    }

    public function fromStop(): BelongsTo
    {
        return $this->belongsTo(GtfsStop::class, 'from_stop_id');
    }

    public function toStop(): BelongsTo
    {
        return $this->belongsTo(GtfsStop::class, 'to_stop_id');
    }
}
