<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'file_name',
    ];

    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'report_images')
            ->withPivot('id');
    }

    public function url(): ?string
    {
        if (! Storage::disk('public')->exists($this->file_name)) {
            return null;
        }

        return Storage::disk('public')->url($this->file_name);
    }
}
