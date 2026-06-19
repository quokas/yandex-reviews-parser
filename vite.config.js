import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
// 1. ИМПОРТИРУЕМ ПЛАГИН ТАЙЛВИНДА v4
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(), // 2. ДОБАВЛЯЕМ В МАССИВ ПЛАГИНОВ
    ],
    server: {
        watch: {
            ignored: ['**/yandex_user_data/**', '**/url.txt', '**/result.json'],
        },
    },
});
