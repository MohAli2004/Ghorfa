<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertySearch extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'filters',
        'location_query',
        'latitude',
        'longitude',
        'results_count',
    ];

    protected $casts = [
        'filters' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'results_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
