document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const profileId = urlParams.get('id');

    if (!profileId) {
        window.location.href = 'index.html';
        return;
    }

    const response = await API.getProfileById(profileId);
    
    if (!response || !response.success || !response.data) {
        document.getElementById('profile-name').textContent = 'Анкета не найдена';
        return;
    }

    const profile = response.data;

    // Заполнение текстов
    document.title = `WeToo — Анкета ${profile.name}`;
    document.getElementById('profile-name').textContent = `${profile.name}, ${profile.age}`;
    document.getElementById('profile-city').textContent = profile.city_name;
    document.getElementById('profile-age').textContent = profile.age;
    document.getElementById('profile-about').textContent = profile.about;

    if (profile.is_vip == 1) {
        document.getElementById('profile-vip-badge').style.display = 'inline-block';
    }

    // Кнопки контактов (проверка наличия данных)
    const contactBtn = document.getElementById('profile-contact-btn');
    if (profile.telegram) {
        // Форматируем юзернейм tg
        const username = profile.telegram.replace('@', '');
        contactBtn.href = `https://t.me/${username}`;
        contactBtn.style.display = 'inline-block';
    }

    const phoneBtn = document.getElementById('profile-phone-btn');
    if (profile.phone) {
        phoneBtn.href = `tel:${profile.phone}`;
        phoneBtn.style.display = 'inline-block';
        phoneBtn.addEventListener('click', (e) => {
            phoneBtn.textContent = profile.phone;
        });
    }

    // Фотогалерея
    const mainPhoto = document.getElementById('profile-main-photo');
    const thumbnailsContainer = document.getElementById('profile-thumbnails');

    // Массив всех картинок анкеты
    const photos = profile.photos && profile.photos.length > 0 
        ? profile.photos 
        : [profile.main_photo || '../uploads/no-avatar.png'];

    // Ставим главную
    mainPhoto.src = photos[0];

    // Отрисовка миниатюр
    if (photos.length > 1) {
        thumbnailsContainer.innerHTML = photos.map((src, idx) => `
            <img src="${src}" class="thumb-img ${idx === 0 ? 'active' : ''}" alt="Миниатюра">
        `).join('');

        // Клики по миниатюрам
        const thumbs = thumbnailsContainer.getElementsByClassName('thumb-img');
        for (let i = 0; i < thumbs.length; i++) {
            thumbs[i].addEventListener('click', function() {
                for (let t of thumbs) t.classList.remove('active');
                this.classList.add('active');
                mainPhoto.src = this.src;
            });
        }
    }
});
