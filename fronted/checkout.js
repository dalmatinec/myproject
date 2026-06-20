document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('add-profile-form');
    const citySelect = document.getElementById('form-city');
    const plansContainer = document.getElementById('plans-container');
    const selectedPlanInput = document.getElementById('selected-plan-id');
    const fileUpload = document.getElementById('file-upload');
    const previewGrid = document.getElementById('preview-grid');

    let uploadedImagesUrls = []; // Массив ссылок, возвращенных сервером

    // 1. Загрузка городов
    const citiesResponse = await API.getCities();
    if (citiesResponse && citiesResponse.success) {
        citiesResponse.data.forEach(city => {
            const op = document.createElement('option');
            op.value = city.id;
            op.textContent = city.name;
            citySelect.appendChild(op);
        });
    }

    // 2. Загрузка тарифов из plans.php
    const plansResponse = await API.getPlans();
    if (plansResponse && plansResponse.success) {
        plansContainer.innerHTML = plansResponse.data.map((plan, idx) => `
            <div class="plan-card-wrapper">
                <div class="plan-card ${plan.is_vip == 1 ? 'vip-plan' : ''} ${idx === 0 ? 'selected' : ''}" data-id="${plan.id}">
                    <div class="plan-title">${plan.name}</div>
                    <div class="plan-price">${plan.price} руб.</div>
                    <div class="plan-features">${plan.description || ''}</div>
                </div>
            </div>
        `).join('');

        // Установка дефолтного тарифа
        if (plansResponse.data[0]) selectedPlanInput.value = plansResponse.data[0].id;

        // Клик по карточкам тарифа
        const cards = plansContainer.getElementsByClassName('plan-card');
        for (let card of cards) {
            card.addEventListener('click', function() {
                for (let c of cards) c.classList.remove('selected');
                this.classList.add('selected');
                selectedPlanInput.value = this.getAttribute('data-id');
            });
        }
    }

    // 3. Асинхронная загрузка файлов при выборе
    fileUpload.addEventListener('change', async () => {
        const files = Array.from(fileUpload.files);
        if (uploadedImagesUrls.length + files.length > 5) {
            alert('Максимально можно загрузить до 5 фотографий.');
            return;
        }

        for (let file of files) {
            const response = await API.uploadPhoto(file);
            if (response && response.success && response.url) {
                uploadedImagesUrls.push(response.url);
                renderPreviews();
            } else {
                alert(`Не удалось загрузить файл: ${file.name}`);
            }
        }
        fileUpload.value = ''; // Сброс инпута
    });

    function renderPreviews() {
        previewGrid.innerHTML = uploadedImagesUrls.map((url, idx) => `
            <div class="preview-box">
                <img src="${url}">
                <button type="button" class="remove-preview-btn" data-index="${idx}">&times;</button>
            </div>
        `).join('');

        // Слушатели удаления фото
        const delBtns = previewGrid.getElementsByClassName('remove-preview-btn');
        for (let btn of delBtns) {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                uploadedImagesUrls.splice(idx, 1);
                renderPreviews();
            });
        }
    }

    // 4. Отправка формы, связка анкеты и инициация платежа
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (uploadedImagesUrls.length === 0) {
            alert('Пожалуйста, загрузите хотя бы одну фотографию.');
            return;
        }

        const planId = selectedPlanInput.value;
        if (!planId) {
            alert('Пожалуйста, выберите тариф.');
            return;
        }

        const profileData = {
            name: document.getElementById('form-name').value,
            age: parseInt(document.getElementById('form-age').value),
            city_id: citySelect.value,
            telegram: document.getElementById('form-telegram').value,
            phone: document.getElementById('form-phone').value,
            about: document.getElementById('form-about').value,
            main_photo: uploadedImagesUrls[0], // Первое фото — основное
            photos: uploadedImagesUrls // Полный массив
        };

        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Создание анкеты...';

        // Создаем анкету
        const profileRes = await API.createProfile(profileData);

        if (profileRes && profileRes.success && profileRes.profile_id) {
            submitBtn.textContent = 'Перенаправление на оплату...';
            
            // Запускаем сессию оплаты через payments.php
            const paymentRes = await API.createPayment(profileRes.profile_id, planId);
            
            if (paymentRes && paymentRes.success && paymentRes.redirect_url) {
                // Подключаем ТГ-интеграцию (telegram.php) для отправки лога/уведомления боту
                await API.initTelegramBot({
                    event: 'new_profile_pending',
                    profile_id: profileRes.profile_id,
                    telegram: profileData.telegram
                });

                // Уходим на платежный шлюз
                window.location.href = paymentRes.redirect_url;
            } else {
                alert('Анкета создана, но не удалось инициировать платеж. Обратитесь в поддержку.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Перейти к оплате';
            }
        } else {
            alert(profileRes.error || 'Ошибка при сохранении анкеты.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Перейти к оплате';
        }
    });
});
