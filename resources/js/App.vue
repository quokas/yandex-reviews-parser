<template>
  <div
    class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8"
  >
    <!-- ЭКРАН 1: ФОРМА АВТОРИЗАЦИИ (Если пользователь НЕ вошел) -->
    <div v-if="!isLoggedIn" class="sm:mx-auto w-full max-w-md">
      <div class="text-center">
        <h2 class="text-3xl font-extrabold text-slate-900">Вход в систему</h2>
        <p class="mt-2 text-sm text-slate-600">Парсер отзывов Яндекс.Карт</p>
      </div>

      <div
        class="mt-8 bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10 border border-slate-200"
      >
        <div
          v-if="authError"
          class="mb-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded-md text-sm"
        >
          {{ authError }}
        </div>

        <form class="space-y-6" @submit.prevent="handleLogin">
          <div>
            <label class="block text-sm font-medium text-slate-700">Email адрес</label>
            <input
              v-model="form.email"
              type="email"
              required
              class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Пароль</label>
            <input
              v-model="form.password"
              type="password"
              required
              class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            />
          </div>

          <button
            type="submit"
            :disabled="isSubmitting"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 transition-colors"
          >
            {{ isSubmitting ? "Вход..." : "Войти" }}
          </button>
        </form>

        <div
          class="mt-6 bg-amber-50 border border-amber-200 rounded-md p-4 text-xs text-amber-800"
        >
          <strong>Данные для входа проверяющего:</strong><br />
          Email: admin@example.com / Пароль: password
        </div>
      </div>
    </div>

    <!-- ЭКРАН 2: РАБОЧАЯ ПАНЕЛЬ ПАРСЕРА (Если пользователь УСПЕШНО вошел) -->
    <div v-else class="max-w-4xl w-full mx-auto px-4">
      <!-- Шапка личного кабинета -->
      <div
        class="bg-white p-6 rounded-lg shadow border border-slate-200 flex justify-between items-center mb-6"
      >
        <div>
          <h1 class="text-xl font-bold text-slate-900">
            Добро пожаловать, {{ user?.name }}!
          </h1>
          <p class="text-sm text-slate-500">
            Вы успешно авторизовались через Laravel Sanctum.
          </p>
        </div>
        <button
          @click="handleLogout"
          class="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors"
        >
          Выйти
        </button>
      </div>

      <!-- Форма ввода ссылки на Яндекс.Карты -->
      <div class="bg-white p-6 rounded-lg shadow border border-slate-200 mb-6">
        <h3 class="text-lg font-medium text-slate-900 mb-2">
          Интеграция с Яндекс.Картами
        </h3>
        <p class="text-sm text-slate-500 mb-4">
          Вставьте ссылку на организацию (с числовым ID на конце)
        </p>

        <form @submit.prevent="handleParse" class="flex gap-4">
          <input
            v-model="yandexUrl"
            type="url"
            required
            placeholder="https://yandex.ru..."
            class="block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
          />
          <button
            type="submit"
            :disabled="isParsing"
            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium text-sm rounded-md shadow-sm transition-colors whitespace-nowrap"
          >
            {{ isParsing ? "Парсинг..." : "Загрузить отзывы" }}
          </button>
        </form>

        <div
          v-if="parseError"
          class="mt-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded-md text-sm"
        >
          {{ parseError }}
        </div>
      </div>

      <!-- Лента отзывов организации из базы данных -->
      <div
        v-if="organization"
        class="bg-white p-6 rounded-lg shadow border border-slate-200"
      >
        <div class="border-b border-slate-200 pb-4 mb-4 flex justify-between items-start">
          <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ organization.name }}</h2>
            <p class="text-sm text-slate-500 mt-1">
              Ссылка:
              <a
                :href="organization.yandex_url"
                target="_blank"
                class="text-blue-600 hover:underline"
                >{{ organization.yandex_url }}</a
              >
            </p>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold text-amber-500">
              ⭐ {{ organization.rating }}
            </div>
            <div class="text-xs text-slate-500">
              На основе {{ organization.review_count }} отзывов
            </div>
          </div>
        </div>

        <!-- Состояние подгрузки пагинации -->
        <div v-if="isLoadingReviews" class="text-center py-12 text-slate-500">
          Загрузка отзывов...
        </div>

        <!-- Список элементов -->
        <div v-else-if="reviews.length" class="space-y-4">
          <div
            v-for="(review, index) in reviews"
            :key="index"
            class="p-4 bg-slate-50 border border-slate-200 rounded-lg"
          >
            <div class="flex justify-between items-center mb-2">
              <span class="font-semibold text-slate-900">{{ review.author_name }}</span>
              <div class="flex items-center gap-4">
                <span class="text-amber-500 font-medium">★ {{ review.stars }}</span>
                <span class="text-xs text-slate-400">{{ review.date }}</span>
              </div>
            </div>
            <p class="text-sm text-slate-700 leading-relaxed">{{ review.text }}</p>
          </div>

          <!-- Кнопки переключения страниц пагинации -->
          <div
            class="flex justify-between items-center pt-4 border-t border-slate-200 mt-6"
          >
            <button
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-50 transition-colors"
            >
              Назад
            </button>
            <span class="text-sm text-slate-600"
              >Страница {{ pagination.current_page }} из {{ pagination.last_page }}</span
            >
            <button
              :disabled="pagination.current_page === pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-4 py-2 border border-slate-300 rounded-md text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-50 transition-colors"
            >
              Вперед
            </button>
          </div>
        </div>

        <div v-else class="text-center py-12 text-slate-400">
          Отзывы отсутствуют в базе данных.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

const isLoggedIn = ref(false);
const user = ref(null);
const authError = ref("");
const isSubmitting = ref(false);

const yandexUrl = ref("");
const isParsing = ref(false);
const parseError = ref("");

const organization = ref(null);
const reviews = ref([]);
const isLoadingReviews = ref(false);
const pagination = ref({ current_page: 1, last_page: 1 });

const form = ref({ email: "", password: "" });

const checkAuth = async () => {
  try {
    const response = await axios.get("/api/user");
    user.value = response.data;
    isLoggedIn.value = true;
  } catch (err) {
    isLoggedIn.value = false;
  }
};

const handleLogin = async () => {
  authError.value = "";
  isSubmitting.value = true;
  try {
    const response = await axios.post("/api/login", form.value);
    user.value = response.data.user;
    isLoggedIn.value = true;
  } catch (err) {
    authError.value = err.response?.data?.message || "Неверный email или пароль.";
  } finally {
    isSubmitting.value = false;
  }
};

const handleLogout = async () => {
  try {
    await axios.post("/api/logout");
  } finally {
    isLoggedIn.value = false;
    user.value = null;
    organization.value = null;
    reviews.value = [];
  }
};

const handleParse = async () => {
  parseError.value = "";
  isParsing.value = true;
  try {
    const response = await axios.post("/api/parse", { url: yandexUrl.value });
    await loadReviews(response.data.organization_id, 1);
    yandexUrl.value = "";
  } catch (err) {
    parseError.value =
      err.response?.data?.message || "Ошибка парсинга. Проверьте правильность ссылки.";
  } finally {
    isParsing.value = false;
  }
};

const loadReviews = async (orgId, page) => {
  isLoadingReviews.value = true;
  try {
    const response = await axios.get(`/api/organizations/${orgId}/reviews`, {
      params: { page },
    });
    organization.value = response.data.organization;
    reviews.value = response.data.reviews.data;
    pagination.value = {
      current_page: response.data.reviews.current_page,
      last_page: response.data.reviews.last_page,
    };
  } catch (err) {
    console.error(err);
  } finally {
    isLoadingReviews.value = false;
  }
};

const changePage = (newPage) => {
  if (organization.value) {
    loadReviews(organization.value.id, newPage);
  }
};

onMounted(() => {
  checkAuth();
});
</script>
