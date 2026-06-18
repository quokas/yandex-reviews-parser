<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['organization_id', 'author_name', 'date', 'text', 'stars'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
