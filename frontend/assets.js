// =============================================
// WeToo — Assets.js
// Общие функции: API, JWT, модалки, хелперы
// =============================================

(function() {
    'use strict';

    // =============================================
    // 1. API БАЗОВЫЙ URL
    // =============================================
    const API_BASE = '/api';

    // =============================================
    // 2. JWT ХЕЛПЕРЫ
    // =============================================
    const TOKEN_KEY = 'wetoo_admin_token';

    function getToken() {
        return localStorage.getItem(TOKEN_KEY);
    }

    function setToken(token) {
        localStorage.setItem(TOKEN_KEY, token);
    }

    function removeToken() {
        localStorage.removeItem(TOKEN_KEY);
    }

    function isLoggedIn() {
        return !!getToken();
    }

    function getAuthHeaders() {
        const token = getToken();
        return token ? { 'Authorization': 'Bearer ' + token } : {};
    }

    // =============================================
    // 3. УНИВЕРСАЛЬНЫЙ FETCH
    // =============================================
    async function apiFetch(endpoint, options = {}) {
        const url = API_BASE + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            ...getAuthHeaders(),
            ...(options.headers || {})
        };

        const config = {
            ...options,
            headers: headers
        };

        // Для FormData удаляем Content-Type (браузер сам поставит boundary)
        if (options.body instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                // Если 401 — токен истек
                if (response.status === 401) {
                    removeToken();
                    if (window.location.pathname.includes('admin.html')) {
                        window.location.href = 'login.html';
                    }
                }
                throw new Error(data.error || 'Ошибка запроса');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // =============================================
    // 4. ЗАГРУЗКА ГОРОДОВ
    // =============================================
    async function loadCities(selectElement, placeholder = 'Выберите город...') {
        if (!selectElement) return;

        // Очищаем, оставляя только placeholder
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;

        try {
            const cities = await apiFetch('/profiles/cities');
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.name;
                selectElement.appendChild(option);
            });
        } catch (error) {
            console.error('Ошибка загрузки городов:', error);
        }
    }

    // =============================================
    // 5. ЗАГРУЗКА ТАРИФОВ
    // =============================================
    async function loadPlans(container, selectedId = null) {
        if (!container) return;

        container.innerHTML = '<div style="text-align:center;padding:20px;color:#999;">Загрузка тарифов...</div>';

        try {
            const plans = await apiFetch('/profiles/plans');
            container.innerHTML = '';

            if (!plans || plans.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:20px;color:#999;">Тарифы не найдены</div>';
                return;
            }

            plans.forEach(plan => {
                const div = document.createElement('div');
                div.className = 'plan-option' + (selectedId == plan.id ? ' active' : '');
                div.dataset.id = plan.id;

                const isChecked = selectedId == plan.id ? 'checked' : '';

                div.innerHTML = `
                    <input type="radio" name="plan_id" value="${plan.id}" id="plan_${plan.id}" ${isChecked}>
                    <label for="plan_${plan.id}">
                        <div class="plan-name">${plan.name}</div>
                        <div class="plan-price">${plan.price_usdt} USDT</div>
                        <div class="plan-duration">${plan.duration_days} дней</div>
                    </label>
                `;

                div.addEventListener('click', function(e) {
                    if (e.target.tagName === 'INPUT') return;
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        document.querySelectorAll('.plan-option').forEach(el => el.classList.remove('active'));
                        this.classList.add('active');
                        // Триггерим событие change
                        const event = new Event('change');
                        radio.dispatchEvent(event);
                    }
                });

                // Если radio уже выбран — подсвечиваем
                const radio = div.querySelector('input[type="radio"]');
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.plan-option').forEach(el => el.classList.remove('active'));
                    if (this.checked) {
                        this.closest('.plan-option').classList.add('active');
                    }
                });

                container.appendChild(div);
            });

            // Если есть выбранный — подсвечиваем
            if (selectedId) {
                const selected = container.querySelector(`.plan-option[data-id="${selectedId}"]`);
                if (selected) selected.classList.add('active');
            }

        } catch (error) {
            console.error('Ошибка загрузки тарифов:', error);
            container.innerHTML = '<div style="text-align:center;padding:20px;color:#e74c3c;">Ошибка загрузки тарифов</div>';
        }
    }

    // =============================================
    // 6. БУРГЕР-МЕНЮ (инициализация)
    // =============================================
    function initBurger() {
        const burger = document.getElementById('burgerBtn');
        const nav = document.getElementById('mainNav');

        if (!burger || !nav) return;

        burger.addEventListener('click', function() {
            nav.classList.toggle('open');
            burger.classList.toggle('active');
        });

        // Закрываем при клике на ссылку
        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                nav.classList.remove('open');
                burger.classList.remove('active');
            });
        });
    }

    // =============================================
    // 7. МОДАЛЬНОЕ ОКНО (универсальное)
    // =============================================
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    function initModal(modalId, closeBtnId) {
        const modal = document.getElementById(modalId);
        const closeBtn = document.getElementById(closeBtnId);

        if (!modal) return;

        // Закрытие по крестику
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeModal(modalId);
            });
        }

        // Закрытие по клику на оверлей
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(modalId);
            }
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) {
                closeModal(modalId);
            }
        });
    }

    // =============================================
    // 8. ФОРМАТИРОВАНИЕ ДАТЫ
    // =============================================
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatDateShort(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // =============================================
    // 9. ВАЛИДАЦИЯ TELEGRAM
    // =============================================
    function validateTelegram(username) {
        if (!username) return false;
        username = username.trim();
        if (!username.startsWith('@')) return false;
        if (username.length < 2) return false;
        return /^@[a-zA-Z0-9_]{1,32}$/.test(username);
    }

    // =============================================
    // 10. ВАЛИДАЦИЯ WHATSAPP
    // =============================================
    function validateWhatsapp(phone) {
        if (!phone) return true; // необязательное поле
        phone = phone.replace(/[^0-9+]/g, '');
        return phone.length >= 6;
    }

    // =============================================
    // 11. ВАЛИДАЦИЯ ВОЗРАСТА
    // =============================================
    function validateAge(age) {
        const num = parseInt(age);
        return !isNaN(num) && num >= 18 && num <= 120;
    }

    // =============================================
    // 12. ПРЕДПРОСМОТР ФОТОГРАФИЙ
    // =============================================
    function previewPhotos(inputElement, previewContainer, maxCount = 5) {
        if (!inputElement || !previewContainer) return;

        previewContainer.innerHTML = '';
        const files = Array.from(inputElement.files || []);

        if (files.length === 0) {
            previewContainer.innerHTML = '<div style="color:#999;font-size:13px;text-align:center;padding:10px;">Фото не выбраны</div>';
            return;
        }

        files.forEach((file, index) => {
            if (index >= maxCount) return;

            const reader = new FileReader();
            const div = document.createElement('div');
            div.className = 'preview-item';

            reader.onload = function(e) {
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Фото ${index + 1}">
                    <button class="preview-remove" data-index="${index}" type="button">×</button>
                `;
                previewContainer.appendChild(div);

                // Удаление фото
                div.querySelector('.preview-remove').addEventListener('click', function() {
                    // Удаляем файл из input
                    const dt = new DataTransfer();
                    const currentFiles = Array.from(inputElement.files);
                    const idx = parseInt(this.dataset.index);
                    currentFiles.forEach((f, i) => {
                        if (i !== idx) dt.items.add(f);
                    });
                    inputElement.files = dt.files;
                    // Обновляем превью
                    previewPhotos(inputElement, previewContainer, maxCount);
                });
            };
            reader.readAsDataURL(file);
        });

        // Если файлов больше maxCount — показываем предупреждение
        if (files.length > maxCount) {
            const warn = document.createElement('div');
            warn.className = 'preview-warning';
            warn.textContent = `⚠️ Максимум ${maxCount} фото. Выбрано: ${files.length}`;
            previewContainer.appendChild(warn);
        }
    }

    // =============================================
    // 13. ЗАГРУЗКА ФАЙЛА (универсальная)
    // =============================================
    async function uploadFile(file, type, extraData = {}) {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('photo', file);

        for (const [key, value] of Object.entries(extraData)) {
            formData.append(key, value);
        }

        return apiFetch('/upload', {
            method: 'POST',
            body: formData
        });
    }

    // =============================================
    // 14. СТАТУСЫ АНКЕТ (для админки)
    // =============================================
    const PROFILE_STATUSES = {
        pending: { label: 'Ожидает', color: '#F1C40F', bg: '#FFF8E1' },
        active: { label: 'Активна', color: '#2ECC71', bg: '#E8F8F0' },
        rejected: { label: 'Отклонена', color: '#E74C3C', bg: '#FDEDEC' },
        blocked: { label: 'Заблокирована', color: '#E74C3C', bg: '#FDEDEC' },
        expired: { label: 'Истекла', color: '#95A5A6', bg: '#F4F6F7' }
    };

    function getProfileStatus(status) {
        return PROFILE_STATUSES[status] || { label: status, color: '#666', bg: '#F0F0F0' };
    }

    // =============================================
    // 15. СТАТУСЫ ПЛАТЕЖЕЙ (для админки)
    // =============================================
    const PAYMENT_STATUSES = {
        waiting: { label: 'Ожидает', color: '#F1C40F', bg: '#FFF8E1' },
        confirmed: { label: 'Подтвержден', color: '#2ECC71', bg: '#E8F8F0' },
        rejected: { label: 'Отклонен', color: '#E74C3C', bg: '#FDEDEC' }
    };

    function getPaymentStatus(status) {
        return PAYMENT_STATUSES[status] || { label: status, color: '#666', bg: '#F0F0F0' };
    }

    // =============================================
    // 16. СТАТУСЫ ЖАЛОБ (для админки)
    // =============================================
    const COMPLAINT_STATUSES = {
        new: { label: 'Новая', color: '#E74C3C', bg: '#FDEDEC' },
        reviewed: { label: 'Просмотрена', color: '#F1C40F', bg: '#FFF8E1' },
        resolved: { label: 'Решена', color: '#2ECC71', bg: '#E8F8F0' }
    };

    function getComplaintStatus(status) {
        return COMPLAINT_STATUSES[status] || { label: status, color: '#666', bg: '#F0F0F0' };
    }

    // =============================================
    // 17. ЭКСПОРТ В ГЛОБАЛЬНЫЙ ОБЪЕКТ
    // =============================================
    window.WeToo = {
        // API
        apiFetch,
        API_BASE,

        // JWT
        getToken,
        setToken,
        removeToken,
        isLoggedIn,
        getAuthHeaders,

        // Загрузка данных
        loadCities,
        loadPlans,

        // Модалки
        openModal,
        closeModal,
        initModal,

        // Форматирование
        formatDate,
        formatDateShort,

        // Валидация
        validateTelegram,
        validateWhatsapp,
        validateAge,

        // Фото
        previewPhotos,
        uploadFile,

        // Статусы
        getProfileStatus,
        getPaymentStatus,
        getComplaintStatus,
        PROFILE_STATUSES,
        PAYMENT_STATUSES,
        COMPLAINT_STATUSES,

        // Инициализация
        initBurger
    };

    // Для обратной совместимости (старый код)
    window.apiFetch = apiFetch;
    window.loadCities = loadCities;
    window.loadPlans = loadPlans;
    window.initBurger = initBurger;
    window.initModal = initModal;
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.previewPhotos = previewPhotos;
    window.uploadFile = uploadFile;
    window.getToken = getToken;
    window.setToken = setToken;
    window.removeToken = removeToken;
    window.isLoggedIn = isLoggedIn;
    window.formatDate = formatDate;
    window.formatDateShort = formatDateShort;
    window.getProfileStatus = getProfileStatus;
    window.getPaymentStatus = getPaymentStatus;

    // =============================================
    // 18. АВТО-ИНИЦИАЛИЗАЦИЯ (если есть элементы)
    // =============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Бургер
        initBurger();

        // Модалки с data-modal атрибутом
        document.querySelectorAll('[data-modal]').forEach(el => {
            const modalId = el.dataset.modal;
            const closeId = modalId + 'Close';
            initModal(modalId, closeId);
        });

        // Автозагрузка городов для select[data-cities]
        document.querySelectorAll('select[data-cities]').forEach(select => {
            const placeholder = select.dataset.placeholder || 'Выберите город...';
            loadCities(select, placeholder);
        });

        // Автозагрузка тарифов для container[data-plans]
        document.querySelectorAll('[data-plans]').forEach(container => {
            const selectedId = container.dataset.selected || null;
            loadPlans(container, selectedId);
        });

        // Автопревью фото для input[data-preview]
        document.querySelectorAll('input[data-preview]').forEach(input => {
            const container = document.getElementById(input.dataset.preview);
            const maxCount = parseInt(input.dataset.max) || 5;
            if (container) {
                input.addEventListener('change', function() {
                    previewPhotos(this, container, maxCount);
                });
            }
        });
    });

})();