<?php

// Model tabeli gtfs_feed_versions - historia importów feedu (sukces / pominięty / błąd).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GtfsFeedVersion extends Model
{
    protected $fillable = [
        'feed_version',
        'source_url',
        'status',
        'message',
    ];
}
