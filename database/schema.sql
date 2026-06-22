CREATE DATABASE IF NOT EXISTS privatclub
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE privatclub;

-- Администраторы
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','superadmin') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Города
CREATE TABLE cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Тарифы
CREATE TABLE tariffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Анкеты
CREATE TABLE profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,

    city_id INT NOT NULL,
    tariff_id INT NULL,

    telegram VARCHAR(100),
    whatsapp VARCHAR(100),

    description TEXT,

    status ENUM(
        'pending',
        'active',
        'rejected',
        'expired'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_profile_city
        FOREIGN KEY (city_id)
        REFERENCES cities(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_profile_tariff
        FOREIGN KEY (tariff_id)
        REFERENCES tariffs(id)
        ON DELETE SET NULL
);

-- Фото анкет
CREATE TABLE profile_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,

    profile_id INT NOT NULL,

    file_name VARCHAR(255) NOT NULL,

    is_main BOOLEAN DEFAULT FALSE,

    sort_order INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_photo_profile
        FOREIGN KEY (profile_id)
        REFERENCES profiles(id)
        ON DELETE CASCADE
);

-- Платежи
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    profile_id INT NOT NULL,

    currency ENUM(
        'BTC',
        'ETH',
        'USDT'
    ) NOT NULL,

    amount DECIMAL(18,8) NOT NULL,

    wallet_address VARCHAR(255) NOT NULL,

    txid VARCHAR(255) NULL,

    screenshot VARCHAR(255) NULL,

    status ENUM(
        'pending',
        'confirmed',
        'rejected'
    ) DEFAULT 'pending',

    admin_comment TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,

    CONSTRAINT fk_payment_profile
        FOREIGN KEY (profile_id)
        REFERENCES profiles(id)
        ON DELETE CASCADE
);

-- Новости
CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,

    content LONGTEXT NOT NULL,

    image VARCHAR(255) NULL,

    views INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Жалобы
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,

    profile_id INT NOT NULL,

    reason TEXT NOT NULL,

    status ENUM(
        'new',
        'reviewed',
        'resolved'
    ) DEFAULT 'new',

    admin_comment TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_complaint_profile
        FOREIGN KEY (profile_id)
        REFERENCES profiles(id)
        ON DELETE CASCADE
);

-- Статистика
CREATE TABLE stats (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    profile_id INT NULL,

    action ENUM(
        'profile_view',
        'card_open',
        'telegram_click',
        'whatsapp_click',
        'news_view'
    ) NOT NULL,

    ip VARCHAR(45) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_action(action),
    INDEX idx_profile(profile_id),
    INDEX idx_created(created_at)
);

-- Настройки сайта
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,

    setting_key VARCHAR(100) NOT NULL UNIQUE,

    setting_value LONGTEXT NULL,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);