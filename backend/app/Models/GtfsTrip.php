<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
