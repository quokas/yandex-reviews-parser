<template>
  <div class="min-h-screen bg-gray-50 text-gray-900 font-sans antialiased">
    <!-- Шапка -->
    <header class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center shadow-sm">
      <h1 class="text-xl font-bold tracking-tight text-gray-900">
        Сервис мониторинга отзывов Я.Карт
      </h1>
      <button @click="logout" class="text-sm font-medium text-red-600 hover:text-red-700 transition duration-150">
        Выйти
      </button>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8 space-y-8">
      <!-- Блок ввода ссылки -->
      <section class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">Подключение новой организации</h2>
        <p class="text-sm text-gray-500">Вставьте ссылку на карточку вашей компании в Яндекс.Картах для синхронизации и кэширования данных.</p>
        
        <div class="flex gap-3">
          <input 
            v-model="yandexUrl" 
            type="text" 
            placeholder="https://yandex.ru..." 
            class="flex-1 bg-gray-50 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
            :disabled="isLoading"
          />
          <button 
            @click.prevent="startParsing" 
            :disabled="isLoading || !yandexUrl"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg px-6 py-2.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed transition duration-150 flex items-center gap-2 whitespace-nowrap"
          >
            <span v-if="isLoading" class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
            {{ isLoading ? 'Синхронизация данных...' : 'Подключить и спарсить' }}
          </button>
        </div>

        <!-- Уведомления -->
        <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
          {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
          ✓ {{ successMessage }}
        </div>
      </section>

      <!-- Блок статистики организации -->
      <section v-if="organization" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center md:text-left">
          <div class="border-b md:border-b-0 md:border-r border-gray-100 pb-4 md:pb-0 md:pr-6">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Компания</p>
            <p class="text-lg font-bold text-gray-900 truncate">{{ organization.name }}</p>
          </div>
          <div class="border-b md:border-b-0 md:border-r border-gray-100 pb-4 md:pb-0 md:pr-6">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Средний рейтинг</p>
            <p class="text-lg font-bold text-yellow-500 flex items-center justify-center md:justify-start gap-1">
              ★ {{ organization.rating }}
            </p>
          </div>
          <div class="border-b md:border-b-0 md:border-r border-gray-100 pb-4 md:pb-0 md:pr-6">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Всего оценок</p>
            <p class="text-lg font-bold text-gray-900">{{ organization.rating_count }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Всего отзывов</p>
            <p class="text-lg font-bold text-gray-900">{{ organization.review_count }}</p>
          </div>
        </div>

        <!-- Лента отзывов из кэша -->
        <div class="border-t border-gray-100 pt-6 space-y-4">
          <h3 class="text-md font-semibold text-gray-900 mb-4">Архив отзывов из кэша бэкенда (Страница {{ currentPage }}):</h3>
          
          <div v-for="review in reviews" :key="review.id" class="bg-gray-50 border border-gray-100 rounded-xl p-4 space-y-2 hover:shadow-sm transition duration-150">
            <div class="flex justify-between items-start">
              <div>
                <h4 class="text-sm font-semibold text-gray-900">{{ review.author_name }}</h4>
                <div class="text-xs text-yellow-500 font-bold tracking-tight">
                  <span v-for="star in review.stars" :key="star">★</span>
                </div>
              </div>
              <span class="text-xs text-gray-400 bg-white border border-gray-200 px-2 py-1 rounded-md shadow-2xs">
                Недавно
              </span>
            </div>
            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
              {{ review.text }}
            </p>
          </div>

          <!-- SPA Пагинация по 50 штук (по ТЗ) -->
          <div v-if="lastPage > 1" class="flex justify-between items-center pt-4 border-t border-gray-100">
            <button 
              @click="changePage(currentPage - 1)" 
              :disabled="currentPage === 1"
              class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition duration-150"
            >
              Назад
            </button>
            <span class="text-xs font-medium text-gray-500">
              Страница {{ currentPage }} из {{ lastPage }}
            </span>
            <button 
              @click="changePage(currentPage + 1)" 
              :disabled="currentPage === lastPage"
              class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition duration-150"
            >
              Вперед
            </button>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

axios.defaults.withCredentials = true;
axios.defaults.baseURL = window.location.origin;

const yandexUrl = ref('');
const isLoading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const organization = ref(null);
const reviews = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);

/**
 * Чистая синхронная автоматическая подгрузка без перезагрузок и плашек
 */
const startParsing = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  successMessage.value = '';
  organization.value = null;
  reviews.value = [];
  
  try {
    await axios.get('/sanctum/csrf-cookie');
    
    // PHP-поток будет висеть, пока Chromium честно делает 8 прокруток и выдает JSON
    const response = await axios.post('/api/parse', { url: yandexUrl.value });
    
    successMessage.value = 'Синхронизация с Яндекс.Картами завершена успешно!';
    const orgId = response.data.organization_id; 
    
    // АВТОМАТИЧЕСКИ выкатываем первую страницу отзывов на экран прямо после закрытия бота!
    await fetchReviews(orgId, 1);
    
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Произошла ошибка во время работы парсера.';
  } finally {
    isLoading.value = false;
  }
};

/**
 * Подгрузка отзывов конкретной страницы
 */
const fetchReviews = async (orgId, page) => {
  try {
    const res = await axios.get(`/api/organizations/${orgId}/reviews?page=${page}`);
    organization.value = res.data.organization;
    reviews.value = res.data.reviews.data;
    currentPage.value = res.data.reviews.current_page;
    lastPage.value = res.data.reviews.last_page;
  } catch (e) {
    errorMessage.value = 'Ошибка подгрузки страниц из локальной базы данных.';
  }
};

/**
 * Переключение страниц пагинации в SPA-режиме
 */
const changePage = async (newPage) => {
  if (newPage >= 1 && newPage <= lastPage.value && organization.value) {
    await fetchReviews(organization.value.id, newPage);
    window.scrollTo({ top: 300, behavior: 'smooth' });
  }
};

/**
 * Выход из системы
 */
const logout = async () => {
  try {
    await axios.post('/api/logout');
    window.location.reload();
  } catch (e) {
    window.location.reload();
  }
};
</script>
