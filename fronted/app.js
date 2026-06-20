document.addEventListener('DOMContentLoaded', async () => {
    // Селекторы элементов
    const cityFilter = document.getElementById('city-filter');
    const vipSection = document.getElementById('vip-section');
    const vipTrack = document.getElementById('vip-carousel-track');
    const vipPrev = document.getElementById('vip-prev');
    const vipNext = document.getElementById('vip-next');
    const catalogGrid = document.getElementById('catalog-grid');
    const emptyState = document.getElementById('empty-state');
    const catalogTitle = document.getElementById('catalog-title');
    const newsSection = document.getElementById('news-section');
    const newsGrid = document.getElementById('news-grid');
    const resetFilterBtn = document.getElementById('reset-filter-btn');

    // Инициализация данных из настроек (Логотип, Контакты)
    const settings = await API.getSettings();
    if (settings && settings.success) {
        if (settings.data.title) document.getElementById('site-logo').textContent = settings.data.title;
        if (settings.data.contacts) document.getElementById('footer-contacts').innerHTML = settings.data.contacts;
    }

    // Загрузка городов в селект фильтра
    const citiesResponse = await API.getCities();
    if (citiesResponse && citiesResponse.success) {
        citiesResponse.data.forEach(city => {
            const option = document.createElement('option');
            option.value = city.id;
            option.textContent = city.name;
            cityFilter.appendChild(option);
        });
    }

    // Лента новостей
    const newsResponse = await API.getNews();
    if (newsResponse && newsResponse.success && newsResponse.data.length > 0) {
        newsSection.style.display = 'block';
        newsGrid.innerHTML = newsResponse.data.map(item => `
            <div class="news-card-wrapper">
                <div class="news-card">
                    <div class="news-date">${item.date || ''}</div>
                    <p>${item.text}</p>
                </div>
            </div>
        `).join('');
    }

    // Инициализация каталога и карусели
    loadVipCarousel();
    loadCatalog();

    // Слушатель фильтра
    cityFilter.addEventListener('change', () => {
        loadCatalog(cityFilter.value);
    });

    resetFilterBtn.addEventListener('click', () => {
        cityFilter.value = '';
        loadCatalog();
    });

    // Функция загрузки VIP-анкет и логика карусели
    async function loadVipCarousel() {
        const vipResponse = await API.getProfiles('', true); // true = только VIP
        if (!vipResponse || !vipResponse.success || vipResponse.data.length === 0) {
            vipSection.style.display = 'none';
            return;
        }

        vipSection.style.display = 'block';
        vipTrack.innerHTML = vipResponse.data.map(profile => `
            <div class="carousel-item" data-id="${profile.id}">
                <a href="profile.html?id=${profile.id}" style="text-decoration: none; color: inherit;">
                    <div class="card-img-box">
                        <img src="${profile.main_photo || '../uploads/no-avatar.png'}" class="card-img" alt="${profile.name}">
                    </div>
                    <div class="card-content">
                        <div class="card-title-row">${profile.name}, ${profile.age}</div>
                        <div class="card-city-row">${profile.city_name}</div>
                    </div>
                </a>
            </div>
        `).join('');

        // Скрипт карусели с центральной карточкой
        const items = vipTrack.getElementsByClassName('carousel-item');
        if (items.length === 0) return;

        let currentIndex = 0;
        const itemWidth = 270; // 240px ширина + 30px отступы
        let autoScrollTimer;

        function updateCarousel() {
            // Вычисляем смещение трека для центрирования карточки
            const wrapperWidth = vipTrack.parentElement.clientWidth;
            const offset = (wrapperWidth / 2) - (itemWidth / 2) - (currentIndex * itemWidth);
            vipTrack.style.transform = `translateX(${offset}px)`;

            // Сбрасываем и вешаем класс центральной карточки
            for (let i = 0; i < items.length; i++) {
                items[i].classList.remove('center-card');
            }
            if (items[currentIndex]) {
                items[currentIndex].classList.add('center-card');
            }
        }

        function nextSlide() {
            currentIndex = (currentIndex + 1) % items.length;
            updateCarousel();
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + items.length) % items.length;
            updateCarousel();
        }

        function startAutoPlay() {
            autoScrollTimer = setInterval(nextSlide, 3000); // 3 секунды автопрокрутка
        }

        function stopAutoPlay() {
            clearInterval(autoScrollTimer);
        }

        // Кнопки управления
        vipNext.addEventListener('click', () => { stopAutoPlay(); nextSlide(); startAutoPlay(); });
        vipPrev.addEventListener('click', () => { stopAutoPlay(); prevSlide(); startAutoPlay(); });

        // Пауза при наведении мыши
        vipTrack.parentElement.addEventListener('mouseenter', stopAutoPlay);
        vipTrack.parentElement.addEventListener('mouseleave', startAutoPlay);

        // Корректный старт и ресайз окна
        setTimeout(updateCarousel, 150);
        window.addEventListener('resize', updateCarousel);
        startAutoPlay();
    }

    // Функция загрузки обычного каталога
    async function loadCatalog(cityId = '') {
        catalogGrid.innerHTML = '';
        emptyState.style.display = 'none';

        const response = await API.getProfiles(cityId, false); // false = обычные
        
        if (!response || !response.success || response.data.length === 0) {
            catalogGrid.style.display = 'none';
            emptyState.style.display = 'block';
            catalogTitle.style.display = 'none';
            return;
        }

        catalogGrid.style.display = 'block';
        catalogTitle.style.display = 'block';

        catalogGrid.innerHTML = response.data.map(profile => `
            <div class="catalog-item-wrapper">
                <a href="profile.html?id=${profile.id}" class="catalog-item">
                    <div class="card-img-box">
                        <img src="${profile.main_photo || '../uploads/no-avatar.png'}" class="card-img" alt="${profile.name}">
                    </div>
                    <div class="card-content">
                        <div class="card-title-row">${profile.name}, ${profile.age}</div>
                        <div class="card-city-row">${profile.city_name}</div>
                    </div>
                </a>
            </div>
        `).join('');
    }
});
