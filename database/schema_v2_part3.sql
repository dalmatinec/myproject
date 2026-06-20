-- ============================================================
-- WeToo — SQL-схема базы данных v2.0
-- Часть 3: Триггеры, представления, начальные данные
-- ============================================================

-- ============================================================
-- ТРИГГЕРЫ
-- ============================================================

DELIMITER $$

-- ----------------------------------------------------------
-- Триггер: одобрение платежа
-- При переходе payments.status → approved:
--   - анкета получает статус active
--   - рассчитывается expires_at из плана
--   - рассчитывается vip_until если plan.vip_days > 0
--   - is_vip выставляется в 1 если vip_days > 0
-- При переходе payments.status → rejected:
--   - анкета получает статус payment_rejected
-- ----------------------------------------------------------
CREATE TRIGGER trg_payment_status_change
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    -- Платёж одобрен
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        UPDATE profiles p
        JOIN plans pl ON pl.id = p.plan_id
        SET
            p.status       = 'active',
            p.published_at = NOW(),
            p.expires_at   = DATE_ADD(NOW(), INTERVAL pl.duration_days DAY),
            -- VIP-срок независим от срока размещения
            p.is_vip       = IF(pl.vip_days > 0, 1, 0),
            p.vip_until    = IF(pl.vip_days > 0, DATE_ADD(NOW(), INTERVAL pl.vip_days DAY), NULL)
        WHERE
            p.id = NEW.profile_id
            AND p.status IN ('pending_payment', 'payment_review');
    END IF;

    -- Платёж отклонён
    IF NEW.status = 'rejected' AND OLD.status != 'rejected' THEN
        UPDATE profiles
        SET status = 'payment_rejected'
        WHERE id = NEW.profile_id
          AND status IN ('pending_payment', 'payment_review');
    END IF;
END$$

-- ----------------------------------------------------------
-- Триггер: новый платёж создан
-- Переводит анкету из pending_payment → payment_review
-- ----------------------------------------------------------
CREATE TRIGGER trg_payment_created
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    UPDATE profiles
    SET status = 'payment_review'
    WHERE id = NEW.profile_id
      AND status = 'pending_payment';
END$$

-- ----------------------------------------------------------
-- Триггер: подсчёт просмотров
-- Увеличивает кешированный счётчик view_count в profiles
-- ----------------------------------------------------------
CREATE TRIGGER trg_view_increment
AFTER INSERT ON views
FOR EACH ROW
BEGIN
    UPDATE profiles
    SET view_count = view_count + 1
    WHERE id = NEW.profile_id;
END$$

-- ----------------------------------------------------------
-- Триггер: подсчёт кликов по контактам
-- Увеличивает кешированные счётчики click_telegram / click_whatsapp
-- Расширяемо: добавить ELSEIF для новых типов контактов
-- ----------------------------------------------------------
CREATE TRIGGER trg_contact_click_increment
AFTER INSERT ON contact_clicks
FOR EACH ROW
BEGIN
    IF NEW.contact_type = 'telegram' THEN
        UPDATE profiles SET click_telegram = click_telegram + 1 WHERE id = NEW.profile_id;
    ELSEIF NEW.contact_type = 'whatsapp' THEN
        UPDATE profiles SET click_whatsapp = click_whatsapp + 1 WHERE id = NEW.profile_id;
    END IF;
END$$

-- ----------------------------------------------------------
-- Триггер: автоматическое снятие VIP-статуса
-- Проверяется при каждом обновлении анкеты.
-- Плановое снятие через scheduled event (см. ниже).
-- ----------------------------------------------------------
CREATE TRIGGER trg_profile_vip_check
BEFORE UPDATE ON profiles
FOR EACH ROW
BEGIN
    -- Если VIP-срок истёк — снимаем флаг
    IF NEW.is_vip = 1 AND NEW.vip_until IS NOT NULL AND NEW.vip_until < NOW() THEN
        SET NEW.is_vip = 0;
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- SCHEDULED EVENT: проверка истёкших VIP и анкет
-- Запускается каждый час.
-- Требует: SET GLOBAL event_scheduler = ON;
-- ============================================================
CREATE EVENT IF NOT EXISTS evt_expire_profiles
ON SCHEDULE EVERY 1 HOUR
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Снять VIP у тех, у кого истёк vip_until
    UPDATE profiles
    SET is_vip = 0
    WHERE is_vip = 1
      AND vip_until IS NOT NULL
      AND vip_until < NOW();

    -- Перевести активные анкеты в статус expired, если истёк expires_at
    UPDATE profiles
    SET status = 'expired'
    WHERE status = 'active'
      AND expires_at IS NOT NULL
      AND expires_at < NOW();
END;

-- ============================================================
-- ПРЕДСТАВЛЕНИЯ (VIEWS)
-- ============================================================

-- Активные анкеты с городом, главным фото и контактами
CREATE OR REPLACE VIEW v_active_profiles AS
SELECT
    p.id,
    p.name,
    p.age,
    p.description,
    p.is_vip,
    p.vip_until,
    p.status,
    p.slug,
    p.view_count,
    p.click_telegram,
    p.click_whatsapp,
    p.published_at,
    p.expires_at,
    p.created_at,
    c.id    AS city_id,
    c.name  AS city_name,
    c.slug  AS city_slug,
    -- Telegram из profile_contacts
    (SELECT pc.value FROM profile_contacts pc
     WHERE pc.profile_id = p.id AND pc.type = 'telegram' LIMIT 1) AS telegram,
    -- WhatsApp из profile_contacts
    (SELECT pc.value FROM profile_contacts pc
     WHERE pc.profile_id = p.id AND pc.type = 'whatsapp' LIMIT 1) AS whatsapp,
    -- Главное фото
    (SELECT ph.filename FROM photos ph
     WHERE ph.profile_id = p.id
     ORDER BY ph.is_main DESC, ph.sort_order ASC
     LIMIT 1) AS main_photo
FROM profiles p
JOIN cities c ON c.id = p.city_id
WHERE
    p.status = 'active'
    AND (p.expires_at IS NULL OR p.expires_at > NOW());

-- Полная статистика по анкете (для страницы анкеты и админки)
CREATE OR REPLACE VIEW v_profile_stats AS
SELECT
    p.id,
    p.name,
    p.slug,
    p.status,
    p.view_count,
    p.click_telegram,
    p.click_whatsapp,
    -- Жалобы
    (SELECT COUNT(*) FROM profile_reports pr WHERE pr.profile_id = p.id) AS report_count,
    (SELECT COUNT(*) FROM profile_reports pr WHERE pr.profile_id = p.id AND pr.status = 'new') AS new_report_count,
    -- Конверсия: клики / просмотры
    IF(p.view_count > 0,
       ROUND((p.click_telegram + p.click_whatsapp) / p.view_count * 100, 1),
       0
    ) AS conversion_pct
FROM profiles p;

-- Платежи, ожидающие проверки (для дашборда администратора)
CREATE OR REPLACE VIEW v_pending_payments AS
SELECT
    pay.id           AS payment_id,
    pay.profile_id,
    pay.amount_usd,
    pay.currency,
    pay.txid,
    pay.screenshot_file,
    pay.status       AS payment_status,
    pay.created_at   AS payment_created,
    p.name           AS profile_name,
    p.slug           AS profile_slug,
    c.name           AS city_name
FROM payments pay
JOIN profiles p ON p.id = pay.profile_id
JOIN cities   c ON c.id = p.city_id
WHERE pay.status IN ('pending', 'review')
ORDER BY pay.created_at ASC;

-- ============================================================
-- НАЧАЛЬНЫЕ ДАННЫЕ
-- Только настройки — без тестовых анкет, городов, тарифов.
-- ============================================================

-- Настройки сайта
INSERT INTO settings (setting_key, setting_val, description) VALUES
('site_name',           'WeToo',                     'Название сайта'),
('site_description',    'Каталог анкет с ручной модерацией', 'Описание сайта'),
('site_url',            '',                          'Полный URL сайта (заполнить при установке)'),
('admin_telegram',      '',                          'Telegram администратора для связи (без @)'),
('admin_telegram_label','Написать администратору',   'Текст кнопки связи с администратором'),
('support_email',       '',                          'Email поддержки'),
('max_photos',          '5',                         'Максимум фотографий на анкету'),
('og_image',            '/images/banner.jpg',        'Open Graph изображение по умолчанию'),
('telegram_channel',    '',                          'Telegram-канал для новостей (без @)'),
('currency_note',       'Принимаем оплату в криптовалюте: USDT (TRC20), BTC, ETH', 'Пояснение к оплате'),
('no_crypto_text',      'Нет возможности оплатить криптовалютой? Свяжитесь с администратором.', 'Текст альтернативной оплаты'),
('footer_text',         '© 2024 WeToo. Каталог анкет с ручной модерацией.', 'Подвал сайта'),
('vip_carousel_title',  'VIP-анкеты',               'Заголовок карусели на главной'),
('vip_carousel_count',  '10',                        'Количество карточек в карусели'),
('advantages',          '["✓ Ручная модерация","✓ Проверка оплаты","✓ До 5 фотографий","✓ Размещение по городам"]', 'Преимущества (JSON)'),
('hero_title',          'Каталог анкет с ручной модерацией', 'Заголовок на главной'),
('hero_subtitle',       'Просматривайте анкеты по городам и связывайтесь напрямую через Telegram или WhatsApp', 'Подзаголовок на главной');

-- SEO по умолчанию для каждой страницы
INSERT INTO seo_pages (page_key, title, description, keywords) VALUES
('index',    'WeToo — Каталог анкет с ручной модерацией', 'Просматривайте анкеты по городам и связывайтесь напрямую через Telegram или WhatsApp.', 'анкеты, каталог, telegram, whatsapp'),
('profiles', 'Анкеты — WeToo', 'Все анкеты каталога WeToo. Фильтр по городам.', 'анкеты по городам, каталог'),
('news',     'Новости — WeToo', 'Новости и обновления каталога WeToo.', 'новости'),
('contacts', 'Контакты — WeToo', 'Свяжитесь с администрацией каталога WeToo.', 'контакты, поддержка'),
('submit',   'Разместить анкету — WeToo', 'Разместите свою анкету в каталоге WeToo.', 'разместить анкету');

-- ============================================================
-- КОНЕЦ СХЕМЫ v2.0
-- Администратор создаётся через install.php (см. ниже)
-- ============================================================