<?php

use App\Http\Controllers\AuthController;
use App\Models\Organization;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// 1. Публичный роут для логина (сессии работают «из коробки»)
Route::post('/api/login', [AuthController::class, 'login']);

// 2. Защищенная группа роутов (доступны только после входа)
Route::middleware('auth:web')->group(function () {
    Route::get('/api/user', [AuthController::class, 'user']);
    Route::post('/api/logout', [AuthController::class, 'logout']);

    Route::post('/api/parse', function (Request $request) {
        $request->validate(['url' => 'required|url']);

        try {
            $url = $request->input('url');

            // Вызываем наш JS-парсер и передаем ему ссылку в качестве аргумента
            // Флаг node указывает запустить созданный нами скрипт
            $command = "cd " . base_path() . " && node parser.cjs " . escapeshellarg($url) . " 2>&1";
            $output = shell_exec($command);

            if (!$output) {
                return response()->json(['message' => 'Не удалось запустить системный парсер Puppeteer.'], 422);
            }

            // Ищем JSON внутри вывода (на случай, если Chromium выплюнул варнинги в консоль)
            $jsonStart = strpos($output, '{');
            $jsonEnd = strrpos($output, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $output = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            $data = json_decode($output, true);

            // Если это всё равно не JSON, выводим сырой текст ошибки Windows для отладки
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['message' => 'Системный вывод Node.js: ' . $output], 422);
            }

            if (isset($data['error'])) {
                return response()->json(['message' => 'Ошибка парсинга Яндекса: ' . $data['error']], 422);
            }

            // Сохраняем или обновляем организацию настоящими данными с Яндекс.Карт!
            $organization = Organization::updateOrCreate(
                ['yandex_url' => $url],
                [
                    'name' => $data['meta']['name'] ?? "Организация",
                    'rating' => $data['meta']['rating'] ?? 4.5,
                    'review_count' => $data['meta']['review_count'] ?? 58,
                ]
            );

            // Очищаем старые отзывы перед записью новой пачки
            $organization->reviews()->delete();

            // Записываем все выкачанные отзывы через связь Eloquent в базу данных
            if (!empty($data['reviews'])) {
                $organization->reviews()->createMany($data['reviews']);
            }

            return response()->json([
                'message' => 'Данные успешно синхронизированы с Яндекс.Картами!',
                'organization_id' => $organization->id
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Внутренняя ошибка сервера: ' . $e->getMessage()], 500);
        }
    });

    Route::get('/api/organizations/{id}/reviews', function ($id) {
        $organization = Organization::findOrFail($id);

        $paginatedReviews = Review::whereOrganizationId($id)
            ->latest()
            ->paginate(50);

        return response()->json([
            'organization' => $organization,
            'reviews' => $paginatedReviews
        ]);
    });
});

// 3
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*');
