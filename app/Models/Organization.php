<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['yandex_url', 'name', 'rating', 'review_count'];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
