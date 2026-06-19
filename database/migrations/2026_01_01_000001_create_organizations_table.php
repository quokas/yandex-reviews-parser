<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('yandex_url')->unique();
            $table->string('name');
            $table->decimal('rating', 2, 1);
            $table->integer('rating_count'); // ПРОВЕРЬ НАЛИЧИЕ ЭТОЙ СТРОКИ
            $table->integer('review_count'); // ПРОВЕРЬ НАЛИЧИЕ ЭТОЙ СТРОКИ
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
