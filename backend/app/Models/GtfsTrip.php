<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GtfsTrip extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'route_id',
        'service_id',
        'shape_id',
        'direction_id',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(GtfsRoute::class, 'route_id');
    }
}
