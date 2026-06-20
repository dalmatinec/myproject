-- ============================================================
-- WeToo — SQL-схема базы данных v2.0 (финальная)
-- Часть 1: Основные таблицы
-- Кодировка: utf8mb4
-- Движок: InnoDB
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS wetoo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wetoo;

-- ============================================================
-- ТАБЛИЦА: admins
-- Администраторы системы.
-- Первый администратор создаётся через установщик (install.php).
-- Никаких тестовых паролей в схеме.
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username      VARCHAR(64)   NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL                   COMMENT 'bcrypt-хеш пароля',
    display_name  VARCHAR(128)  NOT NULL,
    telegram      VARCHAR(128)  DEFAULT NULL               COMMENT 'Telegram администратора',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    last_login_at DATETIME      DEFAULT NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_username (username),
    INDEX idx_active   (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Администраторы системы WeToo';

-- ============================================================
-- ТАБЛИЦА: settings
-- Глобальные настройки сайта в формате ключ-значение.
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(128)  NOT NULL UNIQUE COMMENT 'Уникальный ключ',
    setting_val TEXT          DEFAULT NULL    COMMENT 'Значение',
    description VARCHAR(255)  DEFAULT NULL    COMMENT 'Пояснение для администратора',
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Глобальные настройки сайта';

-- ============================================================
-- ТАБЛИЦА: seo_pages
-- SEO-метаданные для каждой страницы сайта.
-- Управляются через админку без изменения кода.
-- ============================================================
CREATE TABLE IF NOT EXISTS seo_pages (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    page_key    VARCHAR(128)  NOT NULL UNIQUE COMMENT 'Ключ страницы: index, profiles, news, contacts...',
    title       VARCHAR(255)  DEFAULT NULL    COMMENT 'Meta title',
    description VARCHAR(512)  DEFAULT NULL    COMMENT 'Meta description',
    keywords    VARCHAR(512)  DEFAULT NULL    COMMENT 'Meta keywords',
    og_title    VARCHAR(255)  DEFAULT NULL    COMMENT 'Open Graph заголовок',
    og_description VARCHAR(512) DEFAULT NULL  COMMENT 'Open Graph описание',
    og_image    VARCHAR(255)  DEFAULT NULL    COMMENT 'Open Graph изображение',
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_page_key (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEO-метаданные страниц сайта';

-- ============================================================
-- ТАБЛИЦА: cities
-- Города каталога. Добавляются только через БД или админку.
-- ============================================================
CREATE TABLE IF NOT EXISTS cities (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name       VARCHAR(128)  NOT NULL UNIQUE COMMENT 'Название города',
    slug       VARCHAR(128)  NOT NULL UNIQUE COMMENT 'ЧПУ: almaty, moscow...',
    sort_order SMALLINT      NOT NULL DEFAULT 0,
    is_active  TINYINT(1)    NOT NULL DEFAULT 1,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_slug   (slug),
    INDEX idx_active (is_active),
    INDEX idx_sort   (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Города для фильтрации анкет';

-- ============================================================
-- ТАБЛИЦА: plans
-- Тарифные планы. Цены — в БД, не в коде.
-- is_vip определяет, будет ли анкета помечена VIP при оплате.
-- ============================================================
CREATE TABLE IF NOT EXISTS plans (
    id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    name          VARCHAR(128)   NOT NULL,
    description   TEXT           DEFAULT NULL,
    duration_days SMALLINT       NOT NULL              COMMENT 'Дней размещения',
    vip_days      SMALLINT       NOT NULL DEFAULT 0    COMMENT 'Дней VIP-статуса (0 = без VIP)',
    price_usd     DECIMAL(10,2)  NOT NULL,
    is_active     TINYINT(1)     NOT NULL DEFAULT 1,
    sort_order    SMALLINT       NOT NULL DEFAULT 0,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_active (is_active),
    INDEX idx_sort   (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Тарифные планы размещения';

-- ============================================================
-- ТАБЛИЦА: payment_wallets
-- Крипто-кошельки для приёма оплаты.
-- ============================================================
CREATE TABLE IF NOT EXISTS payment_wallets (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    currency   VARCHAR(32)   NOT NULL   COMMENT 'USDT, BTC, ETH...',
    network    VARCHAR(64)   DEFAULT NULL COMMENT 'TRC20, ERC20, BEP20...',
    address    VARCHAR(255)  NOT NULL   COMMENT 'Адрес кошелька',
    label      VARCHAR(128)  DEFAULT NULL COMMENT 'Подпись для пользователя',
    is_active  TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order SMALLINT      NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Кошельки для приёма криптовалюты';

-- ============================================================
-- ТАБЛИЦА: profiles
-- Анкеты пользователей. Центральная таблица.
-- Контакты вынесены в profile_contacts.
-- Добавлены поля модерации и раздельный срок VIP.
-- ============================================================
CREATE TABLE IF NOT EXISTS profiles (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    city_id         INT UNSIGNED      NOT NULL,
    plan_id         INT UNSIGNED      DEFAULT NULL,

    -- Основные данные
    name            VARCHAR(128)      NOT NULL,
    age             TINYINT UNSIGNED  NOT NULL,
    description     TEXT              DEFAULT NULL,

    -- Статус и видимость
    status          ENUM(
                      'pending_payment',
                      'payment_review',
                      'active',
                      'hidden',
                      'payment_rejected',
                      'expired'
                    ) NOT NULL DEFAULT 'pending_payment'
                    COMMENT 'Статус анкеты',

    -- VIP: отдельный срок действия, независимый от срока размещения
    is_vip          TINYINT(1)        NOT NULL DEFAULT 0  COMMENT 'Активен ли VIP прямо сейчас',
    vip_until       DATETIME          DEFAULT NULL        COMMENT 'Дата окончания VIP-статуса',

    -- SEO и навигация
    slug            VARCHAR(191)      NOT NULL UNIQUE     COMMENT 'ЧПУ URL анкеты',

    -- Кешированные счётчики (обновляются триггерами)
    view_count      INT UNSIGNED      NOT NULL DEFAULT 0,
    click_telegram  INT UNSIGNED      NOT NULL DEFAULT 0  COMMENT 'Кеш кликов Telegram',
    click_whatsapp  INT UNSIGNED      NOT NULL DEFAULT 0  COMMENT 'Кеш кликов WhatsApp',

    -- Даты размещения
    published_at    DATETIME          DEFAULT NULL        COMMENT 'Дата публикации',
    expires_at      DATETIME          DEFAULT NULL        COMMENT 'Дата окончания размещения',

    -- Модерация
    moderated_by    INT UNSIGNED      DEFAULT NULL        COMMENT 'ID администратора, одобрившего анкету',
    moderated_at    DATETIME          DEFAULT NULL        COMMENT 'Дата модерации',
    moderation_note TEXT              DEFAULT NULL        COMMENT 'Причина отклонения или заметка',

    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_city        (city_id),
    INDEX idx_status      (status),
    INDEX idx_vip         (is_vip, vip_until),
    INDEX idx_expires     (expires_at),
    INDEX idx_published   (published_at),
    INDEX idx_slug        (slug),
    INDEX idx_moderated   (moderated_by),

    CONSTRAINT fk_profiles_city      FOREIGN KEY (city_id)       REFERENCES cities(id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_profiles_plan      FOREIGN KEY (plan_id)       REFERENCES plans(id)   ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_profiles_moderator FOREIGN KEY (moderated_by)  REFERENCES admins(id)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Анкеты пользователей каталога WeToo';
