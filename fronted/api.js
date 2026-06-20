/**
 * WeToo API Client
 * Взаимодействует с существующим бэкендом через относительные пути.
 */
const API = {
    // Базовые пути к вашим PHP файлам (поднимаемся на уровень выше из папки frontend)
    URLS: {
        router: '../api/index.php',
        auth: '../auth.php',
        profiles: '../profiles.php',
        cities: '../cities.php',
        plans: '../plans.php',
        payments: '../payments.php',
        news: '../news.php',
        settings: '../settings.php',
        upload: '../upload.php',
        admin: '../admin.php',
        telegram: '../telegram.php'
    },

    // Получение сохраненного JWT токена
    getToken() {
        return localStorage.getItem('wetoo_token');
    },

    // Сохранение JWT токена
    setToken(token) {
        localStorage.setItem('wetoo_token', token);
    },

    // Удаление токена (выход)
    removeToken() {
        localStorage.removeItem('wetoo_token');
    },

    // Базовый метод для fetch-запросов
    async request(url, options = {}) {
        const token = this.getToken();
        
        // Автоматически добавляем заголовки авторизации, если есть токен
        options.headers = {
            ...options.headers,
        };

        if (token) {
            options.headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, options);
            
            // Если токен протух (401), а мы ломимся в админку — сбрасываем его
            if (response.status === 401 && window.location.pathname.includes('admin.html')) {
                this.removeToken();
                window.location.reload();
            }

            return await response.json();
        } catch (error) {
            console.error(`Ошибка запроса к ${url}:`, error);
            return { success: false, error: 'Ошибка соединения с сервером' };
        }
    },

    // --- Модуль Настроек (settings.php) ---
    async getSettings() {
        return await this.request(this.URLS.settings);
    },

    // --- Модуль Городов (cities.php) ---
    async getCities() {
        return await this.request(this.URLS.cities);
    },

    // --- Модуль Тарифов (plans.php) ---
    async getPlans() {
        return await this.request(this.URLS.plans);
    },

    // --- Модуль Новостей (news.php) ---
    async getNews() {
        return await this.request(this.URLS.news);
    },

    // --- Модуль Анкет (profiles.php или api/index.php) ---
    async getProfiles(cityId = '', isVip = null) {
        let url = `${this.URLS.profiles}?`;
        if (cityId) url += `city_id=${cityId}&`;
        if (isVip !== null) url += `vip=${isVip ? 1 : 0}`;
        return await this.request(url);
    },

    async getProfileById(id) {
        return await this.request(`${this.URLS.profiles}?id=${id}`);
    },

    async createProfile(profileData) {
        return await this.request(this.URLS.profiles, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(profileData)
        });
    },

    // --- Загрузка фото (upload.php) ---
    async uploadPhoto(file) {
        const formData = new FormData();
        formData.append('photo', file);

        return await this.request(this.URLS.upload, {
            method: 'POST',
            body: formData
            // Браузер сам выставит нужный Content-Type для FormData
        });
    },

    // --- Оплата (payments.php) ---
    async createPayment(profileId, planId) {
        return await this.request(this.URLS.payments, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ profile_id: profileId, plan_id: planId })
        });
    },

    // --- Авторизация (auth.php) ---
    async login(username, password) {
        const result = await this.request(this.URLS.auth, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        if (result && result.token) {
            this.setToken(result.token);
        }
        return result;
    },

    // --- Управление / Админка (admin.php) ---
    async getAdminProfiles() {
        return await this.request(`${this.URLS.admin}?action=get_profiles`);
    },

    async moderateProfile(id, status) {
        return await this.request(this.URLS.admin, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'moderate', id, status }) // status: 'approved' или 'rejected'
        });
    },

    async saveSettings(settingsData) {
        return await this.request(this.URLS.admin, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_settings', settings: settingsData })
        });
    },

    // --- Интеграция Telegram-бота (telegram.php) ---
    async initTelegramBot(data) {
        return await this.request(this.URLS.telegram, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    }
};

// Скрываем/показываем кнопку админки на главной в зависимости от наличия токена прямо при загрузке скрипта
document.addEventListener('DOMContentLoaded', () => {
    const adminLink = document.getElementById('admin-link');
    if (adminLink) {
        if (API.getToken()) {
            adminLink.style.display = 'inline-block'; // Показываем только если админ залогинен
        } else {
            adminLink.style.display = 'none'; // Скрываем от обычных глаз
        }
    }
});
