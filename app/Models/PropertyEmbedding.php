<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyEmbedding extends Model
{
    protected $fillable = [
        'property_id',
        'model',
        'embedding',
        'source_text',
        'generated_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'generated_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
