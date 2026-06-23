/* ======================================== */
/* НАЧАЛО ЧАСТИ 1: Ядро, Навигация, Копирование, Перевод */
/* ======================================== */

/**
 * PrivatClub - Основной JavaScript файл
 * Версия: 1.0.0
 */

// ========================================
// 1. ОБЪЕКТЫ ЛОКАЛИЗАЦИИ
// ========================================

const translations = {
    ru: {
        // Навигация
        'nav_home': 'Главная',
        'nav_profiles': 'Анкеты',
        'nav_news': 'Новости',
        'nav_contacts': 'Контакты',
        'nav_about': 'О нас',
        'admin_panel_title': 'Панель управления',
        'admin_logout': 'Выйти',
        
        // Герой
        'hero_title': 'Элитное сообщество для премиальных знакомств',
        'hero_subtitle': 'Только проверенные анкеты. Конфиденциальность. Высокий статус.',
        'hero_button': 'Смотреть анкеты',
        'stat_profiles': 'Анкет',
        'stat_vip': 'VIP',
        'stat_views': 'Просмотров',
        
        // О нас
        'about_title': 'О клубе',
        'about_text1': 'PrivatClub — это премиальное сообщество для людей, ценящих качественное общение и конфиденциальность.',
        'about_text2': 'Наш сервис создан для тех, кто ищет серьезные отношения или деловые знакомства на высоком уровне.',
        
        // Контакты
        'contacts_title': 'Контакты',
        'contacts_subtitle': 'Свяжитесь с администрацией PrivatClub или оставьте сообщение',
        'contact_telegram_label': 'Telegram',
        'contact_telegram_placeholder': '@privatclub_support',
        'contact_email_label': 'Email поддержки',
        'contact_email_placeholder': 'support@privatclub.com',
        'contact_workhours_label': 'Часы работы',
        'contact_workhours_placeholder': '10:00 - 22:00 (МСК)',
        
        // Фильтры
        'filter_title': 'Фильтры',
        'filter_city': 'Город',
        'select_city': 'Все города',
        'filter_age': 'Возраст',
        'filter_age_from': 'От',
        'filter_age_to': 'До',
        'filter_vip': 'VIP',
        'filter_vip_only': 'Только VIP',
        'filter_search': 'Поиск',
        'filter_search_placeholder': 'Поиск по имени...',
        'filter_apply': 'Применить',
        'filter_reset': 'Сбросить',
        
        // Кнопки
        'add_profile_btn': 'Подать анкету',
        'login_btn': 'Войти',
        'form_send_btn': 'Отправить сообщение',
        'login_title': 'Вход в систему',
        'login_subtitle': 'Панель управления PrivatClub',
        'login_user_label': 'Логин',
        'login_user_placeholder': 'Введите логин',
        'login_pass_label': 'Пароль',
        'login_pass_placeholder': 'Введите пароль',
        'login_error_text': 'Неверный логин или пароль',
        'login_back_link': 'Вернуться на сайт'
    },
    en: {
        // Navigation
        'nav_home': 'Home',
        'nav_profiles': 'Profiles',
        'nav_news': 'News',
        'nav_contacts': 'Contacts',
        'nav_about': 'About',
        'admin_panel_title': 'Dashboard',
        'admin_logout': 'Logout',
        
        // Hero
        'hero_title': 'Elite community for premium dating',
        'hero_subtitle': 'Verified profiles only. Confidentiality. High status.',
        'hero_button': 'View profiles',
        'stat_profiles': 'Profiles',
        'stat_vip': 'VIP',
        'stat_views': 'Views',
        
        // About
        'about_title': 'About us',
        'about_text1': 'PrivatClub is a premium community for people who value quality communication and confidentiality.',
        'about_text2': 'Our service is designed for those seeking serious relationships or business connections at a high level.',
        
        // Contacts
        'contacts_title': 'Contacts',
        'contacts_subtitle': 'Contact the PrivatClub administration or leave a message',
        'contact_telegram_label': 'Telegram',
        'contact_telegram_placeholder': '@privatclub_support',
        'contact_email_label': 'Support Email',
        'contact_email_placeholder': 'support@privatclub.com',
        'contact_workhours_label': 'Working hours',
        'contact_workhours_placeholder': '10:00 - 22:00 (MSC)',
        
        // Filters
        'filter_title': 'Filters',
        'filter_city': 'City',
        'select_city': 'All cities',
        'filter_age': 'Age',
        'filter_age_from': 'From',
        'filter_age_to': 'To',
        'filter_vip': 'VIP',
        'filter_vip_only': 'VIP only',
        'filter_search': 'Search',
        'filter_search_placeholder': 'Search by name...',
        'filter_apply': 'Apply',
        'filter_reset': 'Reset',
        
        // Buttons
        'add_profile_btn': 'Submit profile',
        'login_btn': 'Login',
        'form_send_btn': 'Send message',
        'login_title': 'Login',
        'login_subtitle': 'PrivatClub Dashboard',
        'login_user_label': 'Username',
        'login_user_placeholder': 'Enter username',
        'login_pass_label': 'Password',
        'login_pass_placeholder': 'Enter password',
        'login_error_text': 'Invalid username or password',
        'login_back_link': 'Back to site'
    }
};

let currentLang = 'ru';

// ========================================
// 2. ИНИЦИАЛИЗАЦИЯ
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    initBurgerMenu();
    initToTopButton();
    initCopyButtons();
    initLanguageToggle();
    translatePage();
});

// ========================================
// 3. БУРГЕР-МЕНЮ
// ========================================

function initBurgerMenu() {
    const burger = document.querySelector('.burger-menu');
    const mobileMenu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('menu-overlay');
    const closeBtn = document.querySelector('.menu-close');
    
    if (!burger || !mobileMenu || !overlay) return;
    
    function toggleMenu() {
        const isOpen = mobileMenu.classList.contains('open');
        mobileMenu.classList.toggle('open');
        overlay.classList.toggle('active');
        burger.classList.toggle('active');
        burger.setAttribute('aria-expanded', !isOpen);
        document.body.style.overflow = isOpen ? '' : 'hidden';
    }
    
    burger.addEventListener('click', toggleMenu);
    
    if (closeBtn) {
        closeBtn.addEventListener('click', toggleMenu);
    }
    
    overlay.addEventListener('click', toggleMenu);
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu.classList.contains('open')) {
            toggleMenu();
        }
    });
}

// ========================================
// 4. КНОПКА НАВЕРХ
// ========================================

function initToTopButton() {
    const btn = document.getElementById('btn-to-top');
    
    if (!btn) return;
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    });
    
    btn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// ========================================
// 5. КОПИРОВАНИЕ
// ========================================

function initCopyButtons() {
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-copy');
        if (!btn) return;
        
        const addressEl = btn.closest('.wallet-item')?.querySelector('.wallet-address');
        if (!addressEl) return;
        
        const address = addressEl.textContent.trim();
        copyToClipboard(address, btn);
    });
}

function copyToClipboard(text, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(function() {
                showCopiedFeedback(btn);
            })
            .catch(function() {
                fallbackCopy(text, btn);
            });
    } else {
        fallbackCopy(text, btn);
    }
}

function fallbackCopy(text, btn) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.pointerEvents = 'none';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        const success = document.execCommand('copy');
        if (success) {
            showCopiedFeedback(btn);
        }
    } catch (e) {
        console.error('Copy failed:', e);
    }
    
    document.body.removeChild(textarea);
}

function showCopiedFeedback(btn) {
    const originalText = btn.textContent.trim();
    btn.classList.add('copied');
    btn.textContent = 'Скопировано';
    
    setTimeout(function() {
        btn.classList.remove('copied');
        btn.textContent = originalText;
    }, 2000);
}

// ========================================
// 6. ПЕРЕКЛЮЧЕНИЕ ЯЗЫКА
// ========================================

function initLanguageToggle() {
    const toggles = document.querySelectorAll('.lang-toggle');
    if (!toggles.length) return;
    
    toggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const lang = this.dataset.lang;
            if (lang && lang !== currentLang) {
                currentLang = lang;
                
                toggles.forEach(function(t) {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                
                translatePage();
            }
        });
    });
    
    toggles.forEach(function(t) {
        if (t.dataset.lang === currentLang) {
            t.classList.add('active');
        }
    });
}

// ========================================
// 7. ПЕРЕВОД СТРАНИЦЫ
// ========================================

function translatePage() {
    const t = translations[currentLang];
    if (!t) return;
    
    document.querySelectorAll('[data-translate]').forEach(function(el) {
        const key = el.dataset.translate;
        if (t[key] !== undefined) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
                if (el.hasAttribute('data-translate-placeholder')) {
                    el.placeholder = t[key];
                } else {
                    el.value = t[key];
                }
            } else {
                el.textContent = t[key];
            }
        }
    });
    
    document.querySelectorAll('[data-translate-placeholder]').forEach(function(el) {
        const key = el.dataset.translatePlaceholder;
        if (t[key] !== undefined) {
            el.placeholder = t[key];
        }
    });
}

/* ======================================== */
/* КОНЕЦ ЧАСТИ 1 */
/* ======================================== */
/* ======================================== */
/* НАЧАЛО ЧАСТИ 2: Логика Админки и Интерактива */
/* ======================================== */

// ========================================
// 8. ПЕРЕКЛЮЧЕНИЕ ТАБОВ В АДМИН-ПАНЕЛИ
// ========================================

function initAdminTabs() {
    const buttons = document.querySelectorAll('.admin-nav-btn');
    if (!buttons.length) return;
    
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            // Обновляем активный класс кнопок
            buttons.forEach(function(b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            
            // Переключаем табы
            document.querySelectorAll('.admin-tab').forEach(function(tab) {
                tab.style.display = 'none';
            });
            
            const targetTab = document.getElementById('tab-' + tabId);
            if (targetTab) {
                targetTab.style.display = 'block';
            }
        });
    });
}

// ========================================
// 9. КАРУСЕЛЬ VIP-АНКЕТ
// ========================================

function initCarousel() {
    const container = document.getElementById('vip-profiles-container');
    const prevBtn = document.querySelector('.carousel-prev');
    const nextBtn = document.querySelector('.carousel-next');
    
    if (!container || !prevBtn || !nextBtn) return;
    
    let currentIndex = 0;
    let items = [];
    let autoPlayInterval = null;
    const visibleCount = 3;
    
    function updateCarousel() {
        const track = container.querySelector('.carousel-track');
        if (!track) return;
        
        const totalItems = items.length;
        if (totalItems === 0) return;
        
        const maxIndex = Math.max(0, totalItems - visibleCount);
        if (currentIndex > maxIndex) currentIndex = maxIndex;
        
        const slideWidth = items[0]?.offsetWidth || 280;
        const gap = 16;
        const offset = currentIndex * (slideWidth + gap);
        
        track.style.transform = 'translateX(-' + offset + 'px)';
    }
    
    function goTo(index) {
        const totalItems = items.length;
        if (totalItems === 0) return;
        
        const maxIndex = Math.max(0, totalItems - visibleCount);
        if (index < 0) index = maxIndex;
        if (index > maxIndex) index = 0;
        
        currentIndex = index;
        updateCarousel();
        resetAutoPlay();
    }
    
    function resetAutoPlay() {
        if (autoPlayInterval) {
            clearInterval(autoPlayInterval);
            autoPlayInterval = null;
        }
        startAutoPlay();
    }
    
    function startAutoPlay() {
        if (items.length <= visibleCount) return;
        
        autoPlayInterval = setInterval(function() {
            const totalItems = items.length;
            const maxIndex = Math.max(0, totalItems - visibleCount);
            const nextIndex = (currentIndex + 1) > maxIndex ? 0 : currentIndex + 1;
            goTo(nextIndex);
        }, 5000);
    }
    
    function rebuildCarousel() {
        const slides = container.querySelectorAll('.carousel-slide');
        items = Array.from(slides);
        
        if (items.length === 0) {
            const emptyPlaceholder = document.getElementById('vip-empty');
            if (emptyPlaceholder) {
                emptyPlaceholder.style.display = 'flex';
            }
            return;
        }
        
        // Создаем трек, если его нет
        let track = container.querySelector('.carousel-track');
        if (!track) {
            track = document.createElement('div');
            track.className = 'carousel-track';
            
            // Переносим слайды в трек
            while (container.firstChild) {
                track.appendChild(container.firstChild);
            }
            container.appendChild(track);
        }
        
        // Обновляем элементы
        items = Array.from(track.children);
        
        // Применяем стили к слайдам
        items.forEach(function(item) {
            item.style.flex = '0 0 280px';
            item.style.minWidth = '0';
        });
        
        // Скрываем пустую заглушку
        const emptyPlaceholder = document.getElementById('vip-empty');
        if (emptyPlaceholder) {
            emptyPlaceholder.style.display = 'none';
        }
        
        currentIndex = 0;
        updateCarousel();
        startAutoPlay();
    }
    
    // Наблюдаем за изменением контента
    const observer = new MutationObserver(function() {
        setTimeout(rebuildCarousel, 100);
    });
    observer.observe(container, { childList: true, subtree: true });
    
    // События кнопок
    prevBtn.addEventListener('click', function() {
        goTo(currentIndex - 1);
    });
    
    nextBtn.addEventListener('click', function() {
        goTo(currentIndex + 1);
    });
    
    // Остановка автопрокрутки при наведении
    const wrapper = document.getElementById('vip-carousel');
    if (wrapper) {
        wrapper.addEventListener('mouseenter', function() {
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
        });
        
        wrapper.addEventListener('mouseleave', function() {
            startAutoPlay();
        });
    }
    
    // Инициализация
    setTimeout(rebuildCarousel, 200);
    
    // Обновление при ресайзе
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            updateCarousel();
        }, 200);
    });
}

// ========================================
// 10. ЛОГИКА ВЫХОДА (LOGOUT)
// ========================================

function initLogout() {
    const logoutBtn = document.getElementById('admin-logout');
    if (!logoutBtn) return;
    
    logoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        fetch('/backend/auth.php?action=logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.href = '/frontend/login.html';
            } else {
                window.location.href = '/frontend/login.html';
            }
        })
        .catch(function() {
            window.location.href = '/frontend/login.html';
        });
    });
}

// ========================================
// 11. ЗАГРУЗКА ГОРОДОВ
// ========================================

function loadCities() {
    const selects = document.querySelectorAll('select[name="city_id"], #filter-city, #profile-city-select');
    if (!selects.length) return;
    
    // Пример данных городов (в реальном проекте - запрос к БД)
    const cities = [
        { id: 1, name: 'Москва' },
        { id: 2, name: 'Санкт-Петербург' },
        { id: 3, name: 'Новосибирск' },
        { id: 4, name: 'Екатеринбург' },
        { id: 5, name: 'Казань' },
        { id: 6, name: 'Краснодар' },
        { id: 7, name: 'Сочи' },
        { id: 8, name: 'Ростов-на-Дону' }
    ];
    
    selects.forEach(function(select) {
        // Сохраняем первый пустой option
        const firstOption = select.querySelector('option[value=""]');
        select.innerHTML = '';
        
        if (firstOption) {
            select.appendChild(firstOption);
        } else {
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = select.id === 'filter-city' ? 'Все города' : 'Выберите город';
            select.appendChild(defaultOption);
        }
        
        cities.forEach(function(city) {
            const option = document.createElement('option');
            option.value = city.id;
            option.textContent = city.name;
            select.appendChild(option);
        });
    });
}

// ========================================
// 12. ЗАГРУЗКА КОНТАКТОВ
// ========================================

function loadContacts() {
    const container = document.getElementById('contacts-container');
    const footerContainer = document.getElementById('footer-contacts');
    const contactInfo = document.getElementById('contacts-info');
    
    // Пример контактов (в реальном проекте - запрос к БД)
    const contacts = [
        { type: 'telegram', value: '@privatclub_support', label: 'Telegram' },
        { type: 'whatsapp', value: '+7 999 999 99 99', label: 'WhatsApp' },
        { type: 'email', value: 'support@privatclub.com', label: 'Email' }
    ];
    
    // Заполняем контакты на главной
    if (container) {
        container.innerHTML = '';
        contacts.forEach(function(contact) {
            const btn = document.createElement('a');
            btn.href = contact.type === 'email' ? 'mailto:' + contact.value : 'https://' + contact.value;
            btn.className = 'btn btn-secondary';
            btn.textContent = contact.label;
            btn.target = '_blank';
            container.appendChild(btn);
        });
    }
    
    // Заполняем контакты в футере
    if (footerContainer) {
        footerContainer.innerHTML = '';
        contacts.slice(0, 2).forEach(function(contact) {
            const link = document.createElement('a');
            link.href = contact.type === 'email' ? 'mailto:' + contact.value : 'https://' + contact.value;
            link.textContent = contact.value;
            link.target = '_blank';
            footerContainer.appendChild(link);
        });
    }
    
    // Заполняем контакты на странице контактов
    if (contactInfo) {
        const telegramEl = document.getElementById('contact-telegram');
        const emailEl = document.getElementById('contact-email');
        const workhoursEl = document.getElementById('contact-workhours');
        
        if (telegramEl) telegramEl.textContent = '@privatclub_support';
        if (emailEl) emailEl.textContent = 'support@privatclub.com';
        if (workhoursEl) workhoursEl.textContent = '10:00 - 22:00 (МСК)';
    }
}

// ========================================
// 13. ЗАГРУЗКА НОВОСТЕЙ
// ========================================

function loadNews() {
    const mainContainer = document.getElementById('news-container');
    const allContainer = document.getElementById('news-grid-all');
    
    // Пример новостей (в реальном проекте - запрос к БД)
    const newsItems = [
        {
            id: 1,
            title: 'Новый сезон PrivatClub открыт',
            excerpt: 'Мы рады объявить о запуске нового сезона с обновленными тарифами и улучшенной модерацией.',
            date: '2026-01-15',
            image: '/images/news1.jpg'
        },
        {
            id: 2,
            title: 'Приватные вечеринки для участников',
            excerpt: 'В этом месяце мы организуем серию закрытых мероприятий для VIP-участников клуба.',
            date: '2026-01-10',
            image: '/images/news2.jpg'
        },
        {
            id: 3,
            title: 'Обновление системы безопасности',
            excerpt: 'Мы внедрили новые протоколы защиты данных для максимальной конфиденциальности.',
            date: '2026-01-05',
            image: '/images/news3.jpg'
        }
    ];
    
    const renderNews = function(container, items, limit) {
        if (!container) return;
        
        const displayItems = limit ? items.slice(0, limit) : items;
        
        if (displayItems.length === 0) {
            const emptyEl = container.querySelector('.empty-placeholder');
            if (emptyEl) emptyEl.style.display = 'flex';
            return;
        }
        
        container.innerHTML = '';
        
        displayItems.forEach(function(news) {
            const card = document.createElement('div');
            card.className = 'news-card';
            card.innerHTML = 
                '<img src="' + news.image + '" alt="' + news.title + '" class="news-card-image" onerror="this.src=\'/images/placeholder.jpg\'">' +
                '<div class="news-card-body">' +
                    '<span class="news-card-date">' + news.date + '</span>' +
                    '<h3 class="news-card-title"><a href="/frontend/news.html?id=' + news.id + '">' + news.title + '</a></h3>' +
                    '<p class="news-card-excerpt">' + news.excerpt + '</p>' +
                '</div>';
            container.appendChild(card);
        });
    };
    
    // Главная страница - 3 новости
    if (mainContainer) {
        renderNews(mainContainer, newsItems, 3);
    }
    
    // Страница всех новостей
    if (allContainer) {
        renderNews(allContainer, newsItems);
    }
}

// ========================================
// 14. ЗАГРУЗКА VIP АНКЕТ
// ========================================

function loadVipProfiles() {
    const container = document.getElementById('vip-profiles-container');
    if (!container) return;
    
    // Очищаем контейнер, сохраняя пустую заглушку
    const emptyPlaceholder = document.getElementById('vip-empty');
    
    // Пример VIP анкет (в реальном проекте - запрос к БД)
    const vipProfiles = [
        {
            id: 1,
            name: 'Александр',
            age: 32,
            city: 'Москва',
            photo: '/images/vip1.jpg',
            is_vip: true
        },
        {
            id: 2,
            name: 'Екатерина',
            age: 28,
            city: 'Санкт-Петербург',
            photo: '/images/vip2.jpg',
            is_vip: true
        },
        {
            id: 3,
            name: 'Михаил',
            age: 35,
            city: 'Сочи',
            photo: '/images/vip3.jpg',
            is_vip: true
        },
        {
            id: 4,
            name: 'Анна',
            age: 26,
            city: 'Казань',
            photo: '/images/vip4.jpg',
            is_vip: true
        }
    ];
    
    if (vipProfiles.length === 0) {
        if (emptyPlaceholder) emptyPlaceholder.style.display = 'flex';
        return;
    }
    
    if (emptyPlaceholder) emptyPlaceholder.style.display = 'none';
    
    // Удаляем старые слайды
    container.querySelectorAll('.carousel-slide').forEach(function(el) {
        el.remove();
    });
    
    vipProfiles.forEach(function(profile) {
        const slide = document.createElement('div');
        slide.className = 'carousel-slide';
        slide.innerHTML = 
            '<div class="vip-card">' +
                '<img src="' + profile.photo + '" alt="' + profile.name + '" class="vip-card-image" onerror="this.src=\'/images/placeholder.jpg\'">' +
                '<div class="vip-card-body">' +
                    '<div class="vip-card-header">' +
                        '<span class="vip-card-name">' + profile.name + '</span>' +
                        '<span class="vip-card-badge">' +
                            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L14.5 9H21L15.5 14L18 21L12 16.5L6 21L8.5 14L3 9H9.5L12 2Z"/></svg>' +
                            'VIP' +
                        '</span>' +
                    '</div>' +
                    '<div class="vip-card-details">' +
                        '<span>' + profile.age + ' лет</span>' +
                        '<span>' + profile.city + '</span>' +
                    '</div>' +
                    '<a href="/frontend/profiles.html?id=' + profile.id + '" class="vip-card-btn">Подробнее</a>' +
                '</div>' +
            '</div>';
        container.appendChild(slide);
    });
}

/* ======================================== */
/* КОНЕЦ ЧАСТИ 2 */
/* ======================================== */
/* ======================================== */
/* НАЧАЛО ЧАСТИ 3: Валидация и Обработка Форм */
/* ======================================== */

// ========================================
// 15. УНИВЕРСАЛЬНАЯ ВАЛИДАЦИЯ
// ========================================

function validateForm(data) {
    const errors = {};
    
    // Проверка на пустоту
    if (data.name && !data.name.trim()) {
        errors.name = 'Поле обязательно для заполнения';
    }
    
    // Проверка email
    if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
        errors.email = 'Введите корректный email';
    }
    
    // Проверка телефона
    if (data.phone && !/^[\+\d\s\-\(\)]{10,20}$/.test(data.phone)) {
        errors.phone = 'Введите корректный номер телефона';
    }
    
    // Проверка возраста
    if (data.age && (data.age < 18 || data.age > 99)) {
        errors.age = 'Возраст должен быть от 18 до 99 лет';
    }
    
    return errors;
}

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const errorEl = document.createElement('div');
    errorEl.className = 'field-error';
    errorEl.textContent = message;
    errorEl.style.color = '#ff4444';
    errorEl.style.fontSize = '12px';
    errorEl.style.marginTop = '4px';
    
    // Удаляем старую ошибку
    const oldError = field.parentElement?.querySelector('.field-error');
    if (oldError) oldError.remove();
    
    if (field.parentElement) {
        field.parentElement.appendChild(errorEl);
    }
    
    field.style.borderColor = '#ff4444';
    
    setTimeout(function() {
        if (field.parentElement) {
            const err = field.parentElement.querySelector('.field-error');
            if (err) err.remove();
            field.style.borderColor = '';
        }
    }, 5000);
}

// ========================================
// 16. ОБРАБОТКА ЛОГИНА
// ========================================

function initLoginForm() {
    const form = document.getElementById('login-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = document.getElementById('login-username')?.value.trim();
        const password = document.getElementById('login-password')?.value.trim();
        const errorEl = document.getElementById('login-error');
        
        if (!username || !password) {
            if (errorEl) {
                errorEl.textContent = 'Заполните все поля';
                errorEl.style.display = 'block';
            }
            return;
        }
        
        // Отправка запроса
        fetch('/backend/auth.php?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                username: username, 
                password: password 
            })
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.success) {
                window.location.href = '/frontend/admin.html';
            } else {
                if (errorEl) {
                    errorEl.textContent = data.error || 'Неверный логин или пароль';
                    errorEl.style.display = 'block';
                }
            }
        })
        .catch(function() {
            if (errorEl) {
                errorEl.textContent = 'Ошибка соединения с сервером';
                errorEl.style.display = 'block';
            }
        });
    });
}

// ========================================
// 17. ОБРАБОТКА СОЗДАНИЯ АНКЕТЫ
// ========================================

function initProfileForm() {
    const form = document.getElementById('add-profile-form');
    if (!form) return;
    
    const photosInput = document.getElementById('profile-photos');
    const previewContainer = document.getElementById('photos-preview');
    let uploadedFiles = [];
    
    if (photosInput) {
        photosInput.addEventListener('change', function(e) {
            const files = Array.from(this.files);
            
            if (uploadedFiles.length + files.length > 5) {
                alert('Максимум 5 фотографий');
                this.value = '';
                return;
            }
            
            files.forEach(function(file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Файл ' + file.name + ' превышает 5MB');
                    return;
                }
                uploadedFiles.push(file);
            });
            
            renderPreviews();
            this.value = '';
        });
    }
    
    function renderPreviews() {
        if (!previewContainer) return;
        previewContainer.innerHTML = '';
        
        uploadedFiles.forEach(function(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'photo-preview-item';
                div.innerHTML = '<img src="' + e.target.result + '" alt="Фото ' + (index + 1) + '">' +
                    '<button type="button" class="remove-photo" data-index="' + index + '">×</button>';
                previewContainer.appendChild(div);
                
                div.querySelector('.remove-photo').addEventListener('click', function() {
                    uploadedFiles.splice(index, 1);
                    renderPreviews();
                });
            };
            reader.readAsDataURL(file);
        });
    }
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        
        // Сбор данных
        const name = document.getElementById('profile-name')?.value.trim();
        const cityId = document.getElementById('profile-city-select')?.value;
        const age = document.getElementById('profile-age')?.value;
        const description = document.getElementById('profile-description')?.value.trim();
        const telegram = document.getElementById('profile-telegram')?.value.trim();
        const whatsapp = document.getElementById('profile-whatsapp')?.value.trim();
        const tariff = document.querySelector('input[name="is_vip"]:checked')?.value || '0';
        
        // Валидация
        if (!name) {
            showFieldError('profile-name', 'Введите имя');
            return;
        }
        if (!cityId) {
            showFieldError('profile-city-select', 'Выберите город');
            return;
        }
        if (!age || age < 18 || age > 99) {
            showFieldError('profile-age', 'Введите возраст от 18 до 99');
            return;
        }
        if (!description || description.length < 10) {
            showFieldError('profile-description', 'Описание должно быть не менее 10 символов');
            return;
        }
        if (uploadedFiles.length === 0) {
            alert('Добавьте хотя бы одну фотографию');
            return;
        }
        
        // Заполняем FormData
        formData.append('name', name);
        formData.append('city_id', cityId);
        formData.append('age', age);
        formData.append('description', description);
        formData.append('telegram', telegram || '');
        formData.append('whatsapp', whatsapp || '');
        formData.append('is_vip', tariff);
        formData.append('status', 'pending');
        
        uploadedFiles.forEach(function(file) {
            formData.append('photos[]', file);
        });
        
        // Отправка
        fetch('/backend/profiles.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.success) {
                window.location.href = '/frontend/payment.html?profile_id=' + data.profile_id;
            } else {
                alert(data.error || 'Ошибка создания анкеты');
            }
        })
        .catch(function() {
            alert('Ошибка соединения с сервером');
        });
    });
}

// ========================================
// 18. ОБРАБОТКА ОПЛАТЫ
// ========================================

function initPaymentForm() {
    const form = document.getElementById('payment-form');
    if (!form) return;
    
    // Получаем profile_id из URL
    const urlParams = new URLSearchParams(window.location.search);
    const profileId = urlParams.get('profile_id');
    
    if (profileId) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'profile_id';
        hiddenInput.value = profileId;
        form.appendChild(hiddenInput);
    }
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const txid = document.getElementById('payment-txid')?.value.trim();
        const receipt = document.getElementById('payment-receipt')?.files[0];
        
        if (!txid) {
            showFieldError('payment-txid', 'Введите хеш транзакции');
            return;
        }
        
        if (!receipt) {
            alert('Загрузите скриншот чека');
            return;
        }
        
        if (receipt.size > 5 * 1024 * 1024) {
            alert('Файл превышает 5MB');
            return;
        }
        
        const formData = new FormData();
        formData.append('profile_id', profileId || '');
        formData.append('transaction_ref', txid);
        formData.append('payment_receipt', receipt);
        
        // Отправка
        fetch('/backend/payments.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.success) {
                window.location.href = '/frontend/success.html';
            } else {
                alert(data.error || 'Ошибка подтверждения платежа');
            }
        })
        .catch(function() {
            alert('Ошибка соединения с сервером');
        });
    });
}

// ========================================
// 19. ОБРАБОТКА ПУБЛИКАЦИИ НОВОСТИ
// ========================================

function initNewsForm() {
    const form = document.getElementById('admin-news-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const title = document.getElementById('news-title')?.value.trim();
        const content = document.getElementById('news-content')?.value.trim();
        const image = document.getElementById('news-image')?.value.trim();
        
        if (!title) {
            showFieldError('news-title', 'Введите заголовок новости');
            return;
        }
        
        if (!content || content.length < 20) {
            showFieldError('news-content', 'Текст новости должен быть не менее 20 символов');
            return;
        }
        
        const data = {
            title: title,
            content: content,
            image: image || null
        };
        
        fetch('/backend/news.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.success) {
                alert('Новость успешно опубликована');
                form.reset();
                // Обновляем список новостей
                loadNews();
            } else {
                alert(data.error || 'Ошибка публикации новости');
            }
        })
        .catch(function() {
            alert('Ошибка соединения с сервером');
        });
    });
}

// ========================================
// 20. ОБРАБОТКА ФОРМЫ ОБРАТНОЙ СВЯЗИ
// ========================================

function initFeedbackForm() {
    const form = document.getElementById('feedback-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const name = document.getElementById('feedback-name')?.value.trim();
        const email = document.getElementById('feedback-email')?.value.trim();
        const subject = document.getElementById('feedback-subject')?.value.trim();
        const message = document.getElementById('feedback-message')?.value.trim();
        
        // Валидация
        if (!name) {
            showFieldError('feedback-name', 'Введите ваше имя');
            return;
        }
        
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFieldError('feedback-email', 'Введите корректный email');
            return;
        }
        
        if (!subject) {
            showFieldError('feedback-subject', 'Введите тему сообщения');
            return;
        }
        
        if (!message || message.length < 10) {
            showFieldError('feedback-message', 'Сообщение должно быть не менее 10 символов');
            return;
        }
        
        const data = {
            name: name,
            email: email,
            subject: subject,
            message: message
        };
        
        // Здесь будет отправка на бэкенд
        console.log('Сообщение отправлено:', data);
        
        // Временный успех
        alert('Сообщение отправлено! Мы свяжемся с вами в ближайшее время.');
        form.reset();
    });
}

// ========================================
// 21. ОБРАБОТКА ФИЛЬТРОВ
// ========================================

function initFilterForm() {
    const form = document.getElementById('filters-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const city = document.getElementById('filter-city')?.value;
        const ageFrom = document.getElementById('filter-age-from')?.value;
        const ageTo = document.getElementById('filter-age-to')?.value;
        const vip = document.getElementById('filter-vip')?.checked;
        const search = document.getElementById('filter-search')?.value.trim();
        
        // Собираем параметры для запроса
        const params = new URLSearchParams();
        if (city) params.append('city_id', city);
        if (ageFrom) params.append('age_from', ageFrom);
        if (ageTo) params.append('age_to', ageTo);
        if (vip) params.append('vip', '1');
        if (search) params.append('search', search);
        
        // Перенаправляем с параметрами
        window.location.href = '/frontend/profiles.html?' + params.toString();
    });
    
    // Обработка кнопки сброса
    const resetBtn = document.getElementById('btn-reset-filters');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            window.location.href = '/frontend/profiles.html';
        });
    }
}

// ========================================
// 22. СОХРАНЕНИЕ КОШЕЛЬКОВ
// ========================================

function initWalletForm() {
    const form = document.getElementById('admin-wallets-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btc = document.getElementById('wallet-btc')?.value.trim();
        const eth = document.getElementById('wallet-eth')?.value.trim();
        const usdt = document.getElementById('wallet-usdt')?.value.trim();
        
        const data = {
            btc: btc || '',
            eth: eth || '',
            usdt: usdt || ''
        };
        
        fetch('/backend/admin.php?action=wallets', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.success) {
                alert('Кошельки сохранены');
                loadWallets();
            } else {
                alert(data.error || 'Ошибка сохранения кошельков');
            }
        })
        .catch(function() {
            alert('Ошибка соединения с сервером');
        });
    });
}

/* ======================================== */
/* КОНЕЦ ЧАСТИ 3 */
/* ======================================== */
/* ======================================== */
/* НАЧАЛО ЧАСТИ 4: API, Модерация и Завершение */
/* ======================================== */

// ========================================
// 23. ЗАГРУЗКА СТАТИСТИКИ ДАШБОРДА
// ========================================

function loadDashboardStats() {
    // Заглушка - в реальном проекте запрос к /backend/admin.php?action=dashboard
    const stats = {
        total_profiles: 156,
        pending_profiles: 8,
        total_news: 12,
        total_views: 3421,
        total_vip: 23
    };
    
    // Обновляем статистику на главной странице
    const profilesCount = document.getElementById('stat-profiles');
    const vipCount = document.getElementById('stat-vip');
    const viewsCount = document.getElementById('stat-views');
    
    if (profilesCount) profilesCount.textContent = stats.total_profiles;
    if (vipCount) vipCount.textContent = stats.total_vip;
    if (viewsCount) viewsCount.textContent = stats.total_views;
    
    // Обновляем статистику в админке
    const statProfiles = document.getElementById('stat-profiles-count');
    const statPending = document.getElementById('stat-pending-count');
    const statNews = document.getElementById('stat-news-count');
    
    if (statProfiles) statProfiles.textContent = stats.total_profiles;
    if (statPending) statPending.textContent = stats.pending_profiles;
    if (statNews) statNews.textContent = stats.total_news;
}

// ========================================
// 24. ЗАГРУЗКА СПИСКА НА МОДЕРАЦИЮ
// ========================================

function loadModerationList() {
    const container = document.getElementById('admin-moderation-list');
    if (!container) return;
    
    // Заглушка - в реальном проекте запрос к /backend/admin.php?action=pending
    const pendingProfiles = [
        {
            id: 101,
            name: 'Дмитрий',
            age: 29,
            city: 'Москва',
            photo: '/images/profile1.jpg',
            created_at: '2026-01-20 14:30'
        },
        {
            id: 102,
            name: 'Ольга',
            age: 24,
            city: 'Санкт-Петербург',
            photo: '/images/profile2.jpg',
            created_at: '2026-01-20 12:15'
        },
        {
            id: 103,
            name: 'Максим',
            age: 33,
            city: 'Казань',
            photo: '/images/profile3.jpg',
            created_at: '2026-01-19 18:45'
        }
    ];
    
    // Очищаем контейнер
    container.innerHTML = '';
    
    if (pendingProfiles.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'admin-empty';
        empty.dataset.translate = 'adm_mod_empty';
        empty.textContent = 'Нет анкет на модерацию';
        container.appendChild(empty);
        return;
    }
    
    pendingProfiles.forEach(function(profile) {
        const item = document.createElement('div');
        item.className = 'admin-profile-item';
        item.dataset.id = profile.id;
        item.innerHTML = 
            '<div class="profile-info">' +
                '<img src="' + profile.photo + '" alt="' + profile.name + '" class="profile-avatar" onerror="this.src=\'/images/placeholder.jpg\'">' +
                '<div>' +
                    '<div class="profile-name">' + profile.name + '</div>' +
                    '<div class="profile-detail">' + profile.age + ' лет, ' + profile.city + '</div>' +
                    '<div class="profile-detail" style="font-size: 12px; color: var(--text-muted);">' + profile.created_at + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="profile-actions">' +
                '<button class="btn-approve" data-id="' + profile.id + '">Одобрить</button>' +
                '<button class="btn-reject" data-id="' + profile.id + '">Отклонить</button>' +
            '</div>';
        container.appendChild(item);
    });
    
    // Инициализируем кнопки модерации
    initModerationButtons();
}

// ========================================
// 25. ЛОГИКА МОДЕРАЦИИ
// ========================================

function initModerationButtons() {
    const approveBtns = document.querySelectorAll('.btn-approve');
    const rejectBtns = document.querySelectorAll('.btn-reject');
    
    approveBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = this.dataset.id;
            moderateProfile(id, 'approve');
        });
    });
    
    rejectBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = this.dataset.id;
            moderateProfile(id, 'reject');
        });
    });
}

function moderateProfile(id, action) {
    // Заглушка - в реальном проекте запрос к /backend/admin.php?action=moderate
    console.log('Модерация анкеты ID:', id, 'Действие:', action);
    
    // Имитация запроса
    const button = document.querySelector('.admin-profile-item[data-id="' + id + '"]');
    if (button) {
        button.style.opacity = '0.5';
        button.style.pointerEvents = 'none';
    }
    
    setTimeout(function() {
        // Удаляем карточку после успешной модерации
        if (button) {
            button.style.transition = 'all 0.3s ease';
            button.style.transform = 'scale(0.9)';
            button.style.opacity = '0';
            
            setTimeout(function() {
                button.remove();
                // Проверяем, остались ли анкеты
                const remaining = document.querySelectorAll('.admin-profile-item');
                if (remaining.length === 0) {
                    const container = document.getElementById('admin-moderation-list');
                    if (container) {
                        const empty = document.createElement('div');
                        empty.className = 'admin-empty';
                        empty.dataset.translate = 'adm_mod_empty';
                        empty.textContent = 'Нет анкет на модерацию';
                        container.appendChild(empty);
                    }
                }
                // Обновляем статистику
                loadDashboardStats();
            }, 300);
        }
        
        // Показываем уведомление
        const message = action === 'approve' ? 'Анкета одобрена' : 'Анкета отклонена';
        showNotification(message, action === 'approve' ? 'success' : 'error');
    }, 1000);
}

// ========================================
// 26. ЗАГРУЗКА КОШЕЛЬКОВ
// ========================================

function loadWallets() {
    // Заглушка - в реальном проекте запрос к /backend/admin.php?action=wallets
    const wallets = {
        btc: '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
        eth: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        usdt: 'TQmR6h6Bf2j7Z5X1Lp3Y8W4c9E6v2Kq4N'
    };
    
    // Заполняем инпуты в админке
    const btcInput = document.getElementById('wallet-btc');
    const ethInput = document.getElementById('wallet-eth');
    const usdtInput = document.getElementById('wallet-usdt');
    
    if (btcInput) btcInput.value = wallets.btc;
    if (ethInput) ethInput.value = wallets.eth;
    if (usdtInput) usdtInput.value = wallets.usdt;
    
    // Заполняем контейнер кошельков на странице оплаты
    const walletsContainer = document.getElementById('wallets-container');
    if (walletsContainer) {
        // Очищаем контейнер
        walletsContainer.innerHTML = '';
        
        const walletItems = [
            { currency: 'BTC', address: wallets.btc },
            { currency: 'ETH', address: wallets.eth },
            { currency: 'USDT (TRC-20)', address: wallets.usdt }
        ];
        
        walletItems.forEach(function(wallet) {
            const item = document.createElement('div');
            item.className = 'wallet-item';
            item.innerHTML = 
                '<span class="wallet-currency">' + wallet.currency + '</span>' +
                '<span class="wallet-address">' + wallet.address + '</span>' +
                '<button class="btn-copy">Копировать</button>';
            walletsContainer.appendChild(item);
        });
        
        // Переинициализируем кнопки копирования
        initCopyButtons();
    }
}

// ========================================
// 27. СТАТИСТИКА АНКЕТ
// ========================================

function loadProfileStats() {
    // Заглушка - получение статистики по анкетам
    const stats = {
        total: 156,
        active: 124,
        pending: 8,
        rejected: 12,
        expired: 12,
        vip: 23,
        total_views: 3421,
        avg_views: 27
    };
    
    // Можно использовать для обновления дополнительных элементов
    const totalElement = document.getElementById('stat-profiles');
    const vipElement = document.getElementById('stat-vip');
    const viewsElement = document.getElementById('stat-views');
    
    if (totalElement) totalElement.textContent = stats.total;
    if (vipElement) vipElement.textContent = stats.vip;
    if (viewsElement) viewsElement.textContent = stats.total_views;
    
    // Скрытая статистика для админки
    console.log('Статистика анкет загружена:', stats);
}

// ========================================
// 28. УВЕДОМЛЕНИЯ
// ========================================

function showNotification(message, type) {
    // Создаем элемент уведомления
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.style.cssText = 
        'position: fixed; bottom: 30px; right: 30px; padding: 16px 24px; border-radius: 12px; ' +
        'color: #fff; font-weight: 500; z-index: 9999; animation: slideIn 0.3s ease; ' +
        'background: ' + (type === 'success' ? '#4CAF50' : '#ff4444') + '; ' +
        'box-shadow: 0 8px 32px rgba(0,0,0,0.3); max-width: 400px;';
    notification.textContent = message;
    
    // Добавляем анимацию
    const style = document.createElement('style');
    style.textContent = 
        '@keyframes slideIn { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } } ' +
        '@keyframes slideOut { from { transform: translateY(0); opacity: 1; } to { transform: translateY(100px); opacity: 0; } }';
    document.head.appendChild(style);
    
    document.body.appendChild(notification);
    
    // Автоматическое удаление через 3 секунды
    setTimeout(function() {
        notification.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 3000);
}

// ========================================
// 29. ПЕРЕНАПРАВЛЕНИЕ ПРИ ОТСУТСТВИИ АВТОРИЗАЦИИ
// ========================================

function checkAuth() {
    // Проверка авторизации для админ-страниц
    const isAdminPage = window.location.pathname.includes('/admin.html');
    const isLoginPage = window.location.pathname.includes('/login.html');
    
    if (isAdminPage) {
        fetch('/backend/auth.php?action=check', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (!data.authenticated) {
                window.location.href = '/frontend/login.html';
            }
        })
        .catch(function() {
            window.location.href = '/frontend/login.html';
        });
    }
    
    if (isLoginPage) {
        fetch('/backend/auth.php?action=check', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) { 
            return response.json(); 
        })
        .then(function(data) {
            if (data.authenticated) {
                window.location.href = '/frontend/admin.html';
            }
        })
        .catch(function() {
            // Ошибка - остаемся на странице логина
        });
    }
}

// ========================================
// 30. ИНИЦИАЛИЗАЦИЯ ВСЕХ ФУНКЦИЙ
// ========================================

// Добавляем в DOMContentLoaded пропущенные функции
document.addEventListener('DOMContentLoaded', function() {
    // Уже инициализировано в Части 1:
    // initBurgerMenu, initToTopButton, initCopyButtons, initLanguageToggle, translatePage
    
    // Инициализация из Части 2:
    initAdminTabs();
    initCarousel();
    initLogout();
    loadCities();
    loadContacts();
    loadNews();
    loadVipProfiles();
    
    // Инициализация из Части 3:
    initLoginForm();
    initProfileForm();
    initPaymentForm();
    initNewsForm();
    initFeedbackForm();
    initFilterForm();
    initWalletForm();
    
    // Инициализация из Части 4:
    loadDashboardStats();
    loadModerationList();
    loadWallets();
    loadProfileStats();
    checkAuth();

// Инициализация городов и тарифов
    loadCitiesList();
    loadTariffsList();
    initCityForm();
    initTariffForm();
    initDeleteButtons();
});

// ========================================
// ГОРОДА И ТАРИФЫ (ДОБАВЛЕНО)
// ========================================

function loadCitiesList() {
    fetch('/backend/admin.php?action=cities')
        .then(response => response.json())
        .then(data => {
            const list = document.getElementById('cities-list');
            if (!list) return;
            list.innerHTML = '';
            if (data.data && data.data.length > 0) {
                data.data.forEach(city => {
                    const li = document.createElement('li');
                    li.className = 'admin-list-item';
                    li.innerHTML = `<span>${city.name}</span><button class="btn-delete" data-id="${city.id}" data-type="city">Удалить</button>`;
                    list.appendChild(li);
                });
            } else {
                list.innerHTML = '<li class="admin-empty">Города не добавлены</li>';
            }
        })
        .catch(() => console.error('Failed to load cities'));
}

function loadTariffsList() {
    fetch('/backend/admin.php?action=tariffs')
        .then(response => response.json())
        .then(data => {
            const list = document.getElementById('tariffs-list');
            if (!list) return;
            list.innerHTML = '';
            if (data.data && data.data.length > 0) {
                data.data.forEach(tariff => {
                    const li = document.createElement('li');
                    li.className = 'admin-list-item';
                    li.innerHTML = `<span>${tariff.name} — ${tariff.price} ₽</span><button class="btn-delete" data-id="${tariff.id}" data-type="tariff">Удалить</button>`;
                    list.appendChild(li);
                });
            } else {
                list.innerHTML = '<li class="admin-empty">Тарифы не добавлены</li>';
            }
        })
        .catch(() => console.error('Failed to load tariffs'));
}

function initCityForm() {
    const form = document.getElementById('admin-city-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const name = document.getElementById('city-name').value.trim();
        if (!name) { alert('Введите название города'); return; }
        fetch('/backend/admin.php?action=cities', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('city-name').value = '';
                loadCitiesList();
            } else {
                alert(data.error || 'Ошибка добавления города');
            }
        })
        .catch(() => alert('Ошибка соединения с сервером'));
    });
}

function initTariffForm() {
    const form = document.getElementById('admin-tariff-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const name = document.getElementById('tariff-name').value.trim();
        const price = parseFloat(document.getElementById('tariff-price').value);
        if (!name) { alert('Введите название тарифа'); return; }
        if (isNaN(price) || price < 0) { alert('Введите корректную цену'); return; }
        fetch('/backend/admin.php?action=tariffs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, price: price })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tariff-name').value = '';
                document.getElementById('tariff-price').value = '';
                loadTariffsList();
            } else {
                alert(data.error || 'Ошибка добавления тарифа');
            }
        })
        .catch(() => alert('Ошибка соединения с сервером'));
    });
}

function initDeleteButtons() {
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete');
        if (!btn) return;
        const id = btn.dataset.id;
        const type = btn.dataset.type;
        if (!confirm(`Удалить ${type === 'city' ? 'город' : 'тариф'}?`)) return;
        fetch(`/backend/admin.php?action=${type}&id=${id}`, { method: 'DELETE' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (type === 'city') loadCitiesList();
                    else loadTariffsList();
                } else {
                    alert(data.error || 'Ошибка удаления');
                }
            })
            .catch(() => alert('Ошибка соединения с сервером'));
    });
}

// ======================================== */
// ФАЙЛ APP.JS УСПЕШНО ЗАВЕРШЕН */
// ======================================== */

/* ======================================== */
/* КОНЕЦ ЧАСТИ 4 */
/* ======================================== */