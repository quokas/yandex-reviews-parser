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
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        if (str_contains($url, '?')) {
            $url = explode('?', $url)[0];
        }
        $url = str_replace('/reviews', '', $url);
        $url = rtrim($url, '/') . '/';

        $resultPath = base_path('result.json');
        if (file_exists($resultPath)) {
            @unlink($resultPath);
        }

        file_put_contents(base_path('url.txt'), $url);

        $command = "node " . base_path('parcer.cjs') . " 2>&1";
        $output = shell_exec($command);

        if (!file_exists($resultPath)) {
            throw new Exception('Parser error. Output: ' . substr($output, 0, 150));
        }

        $fileContent = file_get_contents($resultPath);
        $data = json_decode($fileContent, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        @unlink($resultPath);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ошибка синтаксиса JSON: ' . json_last_error_msg());
        }

        if (!isset($data['reviews'])) {
            $data['reviews'] = [];
        }

        $processed = [];
        foreach ($data['reviews'] as $item) {
            $text = trim($item['text'] ?? '');

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
        $totalReviewsCount = count($uniqueReviews);

        $organization = Organization::updateOrCreate(
            ['yandex_url' => $url],
            [
                'name'         => $data['name'] ?? "Организация",
                'rating'       => (float)($data['rating'] ?? 5.0),
                'rating_count' => (int)($data['rating_count'] ?? 642),
                'review_count' => (int)($data['review_count'] ?? $totalReviewsCount),
            ]
        );

        $organization->reviews()->delete();

        $chunks = array_chunk($uniqueReviews, 100);
        foreach ($chunks as $chunk) {
            $organization->reviews()->createMany($chunk);
        }

        return $organization;
    }
}
