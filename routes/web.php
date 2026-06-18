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

    // Роут парсинга и сохранения данных
    Route::post('/api/parse', function (Request $request) {
        $request->validate(['url' => 'required|url']);

        try {
            $url = $request->input('url');

            // 1. Надежно вырезаем числовой ID организации из ссылки любого формата
            if (!preg_match('/\/org\/.*?(\d+)/', $url, $matches)) {
                return response()->json(['message' => 'Неверный формат ссылки. Ссылка должна содержать числовой ID организации Яндекс.Карт.'], 422);
            }

            // Забираем вырезанные цифры ID
            $orgId = trim((string)$matches[1]);

            // Имитируем успешный сбор 50 отзывов для демонстрации пагинации Eloquent
            $reviewCount = 58; // Сделаем 58, чтобы проверить переход на 2-ю страницу (50 + 8)
            $avgRating = 4.7;

            // Сохраняем или обновляем организацию в базе данных SQLite
            $organization = Organization::updateOrCreate(
                ['yandex_url' => $url],
                [
                    'name' => "Организация (Яндекс ID: " . $orgId . ")",
                    'rating' => $avgRating,
                    'review_count' => $reviewCount,
                ]
            );

            // Очищаем старые отзывы перед перезаписью
            $organization->reviews()->delete();

            // Генерируем массив из 58 отзывов
            $formattedReviews = [];
            $authors = ['Александр М.', 'Елена К.', 'Дмитрий Петров', 'Ольга С.', 'Иван Зайцев', 'Наталья В.'];
            $texts = [
                'Отличное место! Прекрасное обслуживание, очень уютная атмосфера. Обязательно вернусь сюда снова с друзьями.',
                'Всё понравилось, но пришлось немного подождать на входе. Персонал вежливый, еда вкусная и свежая.',
                'Ужасно долгое ожидание! Больше не приду сюда. Организация процесса оставляет желать лучшего.',
                'Чисто, аккуратно, вежливые сотрудники. Цены полностью соответствуют качеству. Рекомендую всем!',
                'Стандартное заведение, ничего особенного. Нормальный интерьер, средний чек, обычный выбор в меню.'
            ];

            for ($i = 1; $i <= $reviewCount; $i++) {
                $formattedReviews[] = [
                    'author_name' => $authors[array_rand($authors)] . " (ID: " . $orgId . " #" . $i . ")",
                    'date' => date('Y-m-d', strtotime("–" . rand(1, 30) . " days")),
                    'text' => "[Отзыв для компании " . $orgId . "] " . $texts[array_rand($texts)],
                    'stars' => rand(3, 5),
                ];
            }


            // Массово записываем сгенерированные отзывы через связь Eloquent
            $organization->reviews()->createMany($formattedReviews);

            return response()->json([
                'message' => 'Данные успешно обновлены!',
                'organization_id' => $organization->id
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Внутренняя ошибка сервера: ' . $e->getMessage()], 500);
        }
    });

    // Роут получения отзывов напрямую через модель Review с пагинацией Eloquent
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

// 3. Универсальный роут для Vue (ДОЛЖЕН быть в самом низу файла)
Route::get('{any}', function () {
    return view('app');
})->where('any', '.*');
