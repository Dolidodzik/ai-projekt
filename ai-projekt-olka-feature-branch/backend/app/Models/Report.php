<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'created_at',
        'status',
        'resolved_by_admin_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }

    public function reportImages(): HasMany
    {
        return $this->hasMany(ReportImage::class);
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'report_images');
    }
}
