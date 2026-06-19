<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    // Разрешаем автоматическое заполнение полей из бэкенда
    protected $fillable = [
        'organization_id',
        'author_name',
        'date',
        'text',
        'stars'
    ];

    /**
     * Связь: Каждый отзыв жестко привязан к конкретной организации
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
