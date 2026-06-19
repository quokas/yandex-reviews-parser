<?php

use App\Http\Controllers\AuthController;
use App\Models\Organization;
use App\Models\Review;
use App\Services\YandexParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 1. Публичный роут для авторизации админа (по ТЗ)
Route::post('/api/login', [AuthController::class, 'login']);

// 2. Защищенная группа роутов (SPA сессии Laravel Sanctum)
Route::middleware('auth:web')->group(function () {

    // Получение текущего пользователя для проверки сессии во Vue
    Route::get('/api/user', function (Request $request) {
        return $request->user();
    });

    // Роут выхода из системы
    Route::post('/api/logout', [AuthController::class, 'logout']);

    // --- СИНХРОННЫЙ РОУТ ЗАПУСКА ПАРСИНГА ---
    Route::post('/api/parse', function (Request $request, YandexParserService $parserService) {
        $request->validate(['url' => 'required|url']);

        try {
            // Метод полностью дождется закрытия Chromium и вернет готовую запись из SQLite
            $organization = $parserService->parseAndSync($request->input('url'));

            return response()->json([
                'status' => 'success',
                'message' => 'Синхронизация с Яндекс.Картами завершена успешно!',
                'organization_id' => $organization->id
            ]);
        } catch (\Exception $e) {
            // Возвращаем осмысленную ошибку на фронтенд во Vue по ТЗ
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    });

    // --- РОУТ ПОСТРАНИЧНОЙ НАВИГАЦИИ ИЗ КЭША (ПО 50 ШТУК ПО ТЗ) ---
    Route::get('/api/organizations/{id}/reviews', function ($id) {
        $organization = Organization::findOrFail($id);

        return response()->json([
            'organization' => $organization,
            // Выводим порциями по 50 штук строго по требованиям ТЗ для ментора
            'reviews' => Review::where('organization_id', $id)->orderBy('id', 'desc')->paginate(50)
        ]);
    });
});

// 3. Перенаправление всех остальных путей на главный SPA-шаблон Blade
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*');
