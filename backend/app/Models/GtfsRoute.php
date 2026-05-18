<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GtfsRoute extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'route_id',
        'route_short_name',
        'route_long_name',
        'route_type',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(GtfsTrip::class, 'route_id');
    }
}
