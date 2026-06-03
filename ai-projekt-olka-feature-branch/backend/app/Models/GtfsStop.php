<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GtfsStop extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stop_id',
        'stop_name',
        'stop_lat',
        'stop_lon',
    ];
}
