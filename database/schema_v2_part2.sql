-- ============================================================
-- WeToo — SQL-схема базы данных v2.0
-- Часть 2: Зависимые таблицы
-- ============================================================

-- ============================================================
-- ТАБЛИЦА: profile_contacts
-- Контакты анкеты: Telegram, WhatsApp и любые будущие соцсети.
-- Расширяется без изменения кода — достаточно добавить новый type.
-- ============================================================
CREATE TABLE IF NOT EXISTS profile_contacts (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    profile_id INT UNSIGNED  NOT NULL,
    type       VARCHAR(32)   NOT NULL   COMMENT 'telegram, whatsapp, instagram, vk...',
    value      VARCHAR(255)  NOT NULL   COMMENT 'Значение контакта: номер, username...',
    label      VARCHAR(128)  DEFAULT NULL COMMENT 'Подпись кнопки, если нужна',
    sort_order TINYINT       NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_profile (profile_id),
    INDEX idx_type    (type),
    UNIQUE KEY uq_profile_type (profile_id, type)  COMMENT 'Один тип контакта на анкету',
    CONSTRAINT fk_contacts_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Контакты анкет: Telegram, WhatsApp и другие';

-- ============================================================
-- ТАБЛИЦА: photos
-- Фотографии анкет. Максимум 5 штук — контроль на уровне API.
-- Поле is_main = 1 — главная фотография анкеты.
-- ============================================================
CREATE TABLE IF NOT EXISTS photos (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    profile_id INT UNSIGNED  NOT NULL,
    filename   VARCHAR(255)  NOT NULL   COMMENT 'Имя файла на сервере',
    mime_type  VARCHAR(64)   NOT NULL   COMMENT 'image/jpeg, image/png, image/webp',
    file_size  INT UNSIGNED  DEFAULT NULL COMMENT 'Размер в байтах',
    is_main    TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = главное фото',
    sort_order TINYINT       NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_profile (profile_id),
    INDEX idx_main    (profile_id, is_main),
    INDEX idx_sort    (profile_id, sort_order),
    CONSTRAINT fk_photos_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Фотографии анкет';

-- ============================================================
-- ТАБЛИЦА: payments
-- Платежи за размещение. Подтверждаются вручную администратором.
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    profile_id      INT UNSIGNED   NOT NULL,
    wallet_id       INT UNSIGNED   DEFAULT NULL COMMENT 'Кошелёк получателя',
    amount_usd      DECIMAL(10,2)  DEFAULT NULL COMMENT 'Сумма в USD',
    currency        VARCHAR(32)    DEFAULT NULL COMMENT 'USDT, BTC...',
    txid            VARCHAR(255)   DEFAULT NULL COMMENT 'ID транзакции блокчейна',
    screenshot_file VARCHAR(255)   DEFAULT NULL COMMENT 'Скриншот подтверждения',
    status          ENUM(
                      'pending',
                      'review',
                      'approved',
                      'rejected'
                    ) NOT NULL DEFAULT 'pending',
    admin_id        INT UNSIGNED   DEFAULT NULL COMMENT 'Кто проверял',
    admin_note      TEXT           DEFAULT NULL COMMENT 'Заметка администратора',
    reviewed_at     DATETIME       DEFAULT NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_profile (profile_id),
    INDEX idx_status  (status),
    INDEX idx_admin   (admin_id),
    CONSTRAINT fk_payments_profile FOREIGN KEY (profile_id) REFERENCES profiles(id)        ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_payments_wallet  FOREIGN KEY (wallet_id)  REFERENCES payment_wallets(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_payments_admin   FOREIGN KEY (admin_id)   REFERENCES admins(id)          ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Платежи за размещение анкет';

-- ============================================================
-- ТАБЛИЦА: views
-- Журнал просмотров анкет.
-- IP используется для базовой дедупликации.
-- ============================================================
CREATE TABLE IF NOT EXISTS views (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    profile_id INT UNSIGNED  NOT NULL,
    ip_address VARCHAR(45)   DEFAULT NULL COMMENT 'IPv4 или IPv6',
    user_agent VARCHAR(512)  DEFAULT NULL,
    viewed_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_profile   (profile_id),
    INDEX idx_viewed_at (viewed_at),
    INDEX idx_dedup     (profile_id, ip_address, viewed_at),
    CONSTRAINT fk_views_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Журнал просмотров анкет';

-- ============================================================
-- ТАБЛИЦА: contact_clicks
-- Клики по контактным кнопкам (Telegram, WhatsApp и др.).
-- Позволяет видеть реальную конверсию: просмотры → клики.
-- Пример: Алина — 300 просмотров, 80 кликов Telegram.
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_clicks (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    profile_id   INT UNSIGNED  NOT NULL,
    contact_type VARCHAR(32)   NOT NULL  COMMENT 'telegram, whatsapp, instagram...',
    ip_address   VARCHAR(45)   DEFAULT NULL,
    user_agent   VARCHAR(512)  DEFAULT NULL,
    clicked_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_profile      (profile_id),
    INDEX idx_type         (contact_type),
    INDEX idx_clicked_at   (clicked_at),
    INDEX idx_profile_type (profile_id, contact_type),
    CONSTRAINT fk_clicks_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Клики по контактным кнопкам анкет';

-- ============================================================
-- ТАБЛИЦА: profile_reports
-- Жалобы на анкеты от пользователей.
-- Статус обрабатывается администратором вручную.
-- ============================================================
CREATE TABLE IF NOT EXISTS profile_reports (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    profile_id INT UNSIGNED  NOT NULL,
    reason     VARCHAR(128)  NOT NULL   COMMENT 'spam, fake, inappropriate, other',
    comment    TEXT          DEFAULT NULL COMMENT 'Комментарий пользователя',
    ip_address VARCHAR(45)   DEFAULT NULL COMMENT 'IP отправителя жалобы',
    status     ENUM(
                 'new',
                 'reviewed',
                 'dismissed',
                 'action_taken'
               ) NOT NULL DEFAULT 'new'
               COMMENT 'new=новая, reviewed=рассмотрена, dismissed=отклонена, action_taken=меры приняты',
    admin_id   INT UNSIGNED  DEFAULT NULL COMMENT 'Кто рассматривал жалобу',
    admin_note TEXT          DEFAULT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_profile (profile_id),
    INDEX idx_status  (status),
    INDEX idx_admin   (admin_id),
    CONSTRAINT fk_reports_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reports_admin   FOREIGN KEY (admin_id)   REFERENCES admins(id)   ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Жалобы пользователей на анкеты';

-- ============================================================
-- ТАБЛИЦА: news
-- Новости и публикации.
-- Готова к синхронизации с Telegram-каналом через telegram_sync.
-- ============================================================
CREATE TABLE IF NOT EXISTS news (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    admin_id     INT UNSIGNED  DEFAULT NULL COMMENT 'Автор-администратор',
    title        VARCHAR(255)  NOT NULL,
    slug         VARCHAR(255)  NOT NULL UNIQUE,
    content      LONGTEXT      NOT NULL,
    preview_image VARCHAR(255) DEFAULT NULL,
    source       ENUM('manual','telegram') NOT NULL DEFAULT 'manual'
                 COMMENT 'manual=вручную, telegram=из Telegram-канала',
    is_published TINYINT(1)   NOT NULL DEFAULT 0,
    published_at DATETIME     DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_published  (is_published, published_at),
    INDEX idx_slug       (slug),
    INDEX idx_admin      (admin_id),
    CONSTRAINT fk_news_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Новости и публикации сайта';

-- ============================================================
-- ТАБЛИЦА: telegram_sync
-- Универсальная таблица синхронизации с Telegram.
-- entity_type: news, profile, announcement — любые будущие сущности.
-- Позволяет один раз написать бота и синхронизировать всё что угодно.
-- ============================================================
CREATE TABLE IF NOT EXISTS telegram_sync (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    entity_type         VARCHAR(64)   NOT NULL  COMMENT 'Тип сущности: news, profile, announcement...',
    entity_id           INT UNSIGNED  NOT NULL  COMMENT 'ID сущности в соответствующей таблице',
    telegram_message_id BIGINT        NOT NULL  COMMENT 'ID сообщения в Telegram',
    telegram_channel    VARCHAR(128)  NOT NULL  COMMENT 'Имя канала (без @)',
    direction           ENUM('outgoing','incoming') NOT NULL DEFAULT 'outgoing'
                        COMMENT 'outgoing=отправлено в TG, incoming=получено из TG',
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_entity     (entity_type, entity_id),
    INDEX idx_tg_message (telegram_message_id, telegram_channel),
    UNIQUE KEY uq_entity_channel (entity_type, entity_id, telegram_channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Синхронизация контента с Telegram-каналами';

SET FOREIGN_KEY_CHECKS = 1;