<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    // Массовое заполнение полей
    protected $fillable = [
        'yandex_url',
        'name',
        'rating',
        'rating_count',
        'review_count'
    ];

    // Отношение к отзывам
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
} // Скобка должна закрывать класс ТОЛЬКО ЗДЕСЬ, в самом конце файла!
