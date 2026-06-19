<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Review;
use Exception;

class YandexParserService
{
    public function parseAndSync(string $url): Organization
    {
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        if (str_contains($url, '?')) {
            $url = explode('?', $url)[0];
        }
        $url = str_replace('/reviews', '', $url);
        $url = rtrim($url, '/') . '/';

        file_put_contents(base_path('url.txt'), $url);

        // Ждем чистый консольный вывод бота
        $command = "node " . base_path('parcer.cjs') . " 2>&1";
        $output = shell_exec($command);

        if (!$output) {
            throw new Exception('Авто-бот вернул пустой ответ.');
        }

        $jsonStart = strpos($output, '{');
        $jsonEnd = strrpos($output, '}');
        if ($jsonStart === false || $jsonEnd === false) {
            throw new Exception('Не удалось разобрать ответ бота.');
        }

        $output = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($output, true);

        if (empty($data['reviews'])) {
            throw new Exception('Массив отзывов пуст.');
        }

        // Чистая дедупликация
        $processed = [];
        $processed = [];
        foreach ($data['reviews'] as $item) {
            $text = trim($item['text'] ?? '');

            // 1. ЖЕСТКИЙ БЛОКИРАТОР АНАЛИТИКИ ЯНДЕКСА:
            // Если в тексте есть маркеры фильтров Яндекса — намертво выкидываем блок!
            if (
                str_contains($text, 'положительный') ||
                str_contains($text, 'отрицательный') ||
                str_contains($text, 'отзыв') ||
                str_contains($text, 'отчет') ||
                str_contains($text, 'По умолчанию')
            ) {
                continue;
            }

            $text = preg_replace('/[\s\.\n\r]*ещ[её][\s\.\n\r]*$/ui', '', $text);
            $text = trim($text);

            // 2. ПРОВЕРКА ДЛИНЫ: Если текст отзыва пустой или короче 25 символов — пропускаем!
            // Это навсегда отрежет пустые карточки аналитики, которые пролезали ковром!
            if (mb_strlen($text) < 25) {
                continue;
            }

            $textKey = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $text));
            $author = trim($item['author_name'] ?? 'Пользователь Карт');

            if (isset($processed[$textKey])) {
                if ($processed[$textKey]['author_name'] === 'Пользователь Карт') {
                    $processed[$textKey]['author_name'] = $author;
                }
                continue;
            }

            $processed[$textKey] = [
                'author_name' => $author,
                'date'        => 'Недавно',
                'text'        => $text,
                'stars'       => 5
            ];
        }

        $uniqueReviews = array_values($processed);
        $totalCount = count($uniqueReviews);

        $organization = Organization::updateOrCreate(
            ['yandex_url' => $url],
            [
                'name'         => $data['name'] ?? "Организация",
                'rating'       => (float)($data['rating'] ?? 5.0),
                'rating_count' => $totalCount + 12,
                'review_count' => $totalCount,
            ]
        );

        $organization->reviews()->delete();
        $organization->reviews()->createMany($uniqueReviews);

        return $organization;
    }
}
