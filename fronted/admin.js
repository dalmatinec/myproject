document.addEventListener('DOMContentLoaded', async () => {
    const loginBlock = document.getElementById('login-block');
    const adminMainBlock = document.getElementById('admin-main-block');
    const loginForm = document.getElementById('admin-login-form');
    const loginError = document.getElementById('login-error');
    const logoutBtn = document.getElementById('logout-btn');
    const profilesTable = document.getElementById('admin-profiles-table');
    const emptyTableMsg = document.getElementById('admin-empty-table');
    const settingsForm = document.getElementById('admin-settings-form');

    // Проверка токена
    if (API.getToken()) {
        showAdminPanel();
    }

    // Авторизация
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginError.style.display = 'none';

        const user = document.getElementById('login-username').value;
        const pass = document.getElementById('login-password').value;

        const res = await API.login(user, pass);
        if (res && res.success) {
            showAdminPanel();
        } else {
            loginError.textContent = res.error || 'Неверный логин или пароль';
            loginError.style.display = 'block';
        }
    });

    // Выход
    logoutBtn.addEventListener('click', () => {
        API.removeToken();
        window.location.reload();
    });

    // Переключение табов
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(this.getAttribute('data-tab')).classList.add('active');
        });
    });

    async function showAdminPanel() {
        loginBlock.style.display = 'none';
        adminMainBlock.style.display = 'block';
        logoutBtn.style.display = 'inline-block';

        loadModerationList();
        loadSettingsIntoForm();
    }

    // Загрузка анкет на модерацию
    async function loadModerationList() {
        profilesTable.innerHTML = '';
        emptyTableMsg.style.display = 'none';

        const res = await API.getAdminProfiles(); // Из admin.php
        if (!res || !res.success || res.data.length === 0) {
            emptyTableMsg.style.display = 'block';
            return;
        }

        res.data.forEach(profile => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><img src="${profile.main_photo || '../uploads/no-avatar.png'}" class="table-thumb"></td>
                <td><strong>${profile.name}</strong>, ${profile.age}</td>
                <td>${profile.city_name}</td>
                <td>TG: ${profile.telegram}<br>Тел: ${profile.phone || '—'}</td>
                <td><span class="badge-admin">${profile.plan_name || 'Обычный'}</span></td>
                <td class="btn-actions-group">
                    <button class="btn btn-primary approve-btn" data-id="${profile.id}">Одобрить</button>
                    <button class="btn btn-danger reject-btn" data-id="${profile.id}">Отклонить</button>
                </td>
            `;
            profilesTable.appendChild(tr);
        });

        // Слушатели кнопок модерации
        const approveBtns = profilesTable.getElementsByClassName('approve-btn');
        const rejectBtns = profilesTable.getElementsByClassName('reject-btn');

        for (let btn of approveBtns) {
            btn.addEventListener('click', async function() {
                const id = this.getAttribute('data-id');
                const actionRes = await API.moderateProfile(id, 'approved');
                if (actionRes && actionRes.success) {
                    // Уведомление в бот (telegram.php) об успешном прохождении модерации
                    await API.initTelegramBot({ event: 'profile_approved', profile_id: id });
                    loadModerationList();
                }
            });
        }

        for (let btn of rejectBtns) {
            btn.addEventListener('click', async function() {
                const id = this.getAttribute('data-id');
                const actionRes = await API.moderateProfile(id, 'rejected');
                if (actionRes && actionRes.success) {
                    loadModerationList();
                }
            });
        }
    }

    // Загрузка текущих настроек в форму
    async function loadSettingsIntoForm() {
        const res = await API.getSettings();
        if (res && res.success && res.data) {
            document.getElementById('settings-title').value = res.data.title || '';
            document.getElementById('settings-tg-link').value = res.data.tg_channel || '';
            document.getElementById('settings-contacts').value = res.data.contacts || '';
            document.getElementById('settings-bot-token').value = res.data.bot_token || '';
        }
    }

    // Сохранение настроек
    settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const settingsData = {
            title: document.getElementById('settings-title').value,
            tg_channel: document.getElementById('settings-tg-link').value,
            contacts: document.getElementById('settings-contacts').value,
            bot_token: document.getElementById('settings-bot-token').value
        };

        const saveRes = await API.saveSettings(settingsData);
        if (saveRes && saveRes.success) {
            alert('Настройки успешно сохранены!');
        } else {
            alert(saveRes.error || 'Не удалось сохранить настройки.');
        }
    });
});
