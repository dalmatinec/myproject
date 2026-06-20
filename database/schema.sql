-- =============================================
-- WeToo Project Database Schema v3.1
-- Production Ready | Финальная версия
-- =============================================

CREATE DATABASE IF NOT EXISTS `wetoo` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `wetoo`;

-- =============================================
-- 1. НАСТРОЙКИ САЙТА
-- =============================================
CREATE TABLE `settings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_title', 'WeToo'),
('site_description', 'Платформа проверенных анкет'),
('site_url', 'https://your-domain.com'),
('telegram_bot_token', ''),
('telegram_channel_username', ''),
('telegram_support_username', ''),
('telegram_exchange_username', ''),
('btc_wallet', ''),
('eth_wallet', ''),
('usdt_trc20_wallet', '');

-- =============================================
-- 2. АДМИНИСТРАТОРЫ
-- =============================================
CREATE TABLE `admins` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'moderator') DEFAULT 'moderator',
    `last_ip` VARCHAR(45),
    `last_login` DATETIME,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 3. ГОРОДА
-- =============================================
CREATE TABLE `cities` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 4. ТАРИФЫ
-- =============================================
CREATE TABLE `plans` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `price_btc` DECIMAL(16,8) DEFAULT 0,
    `price_eth` DECIMAL(16,8) DEFAULT 0,
    `price_usdt` DECIMAL(10,2) DEFAULT 0,
    `duration_days` INT NOT NULL DEFAULT 30,
    `sort_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plans` (`name`, `price_usdt`, `duration_days`, `sort_order`) VALUES
('Обычная', 5.00, 30, 1),
('VIP', 15.00, 30, 2),
('ТОП', 30.00, 30, 3);

-- =============================================
-- 5. АНКЕТЫ
-- =============================================
CREATE TABLE `profiles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `city_id` INT NOT NULL,
    `plan_id` INT NULL,
    
    `name` VARCHAR(100) NOT NULL,
    `age` TINYINT UNSIGNED NOT NULL,
    `description` TEXT NOT NULL,
    `telegram` VARCHAR(100),
    `whatsapp` VARCHAR(20),
    `price` DECIMAL(10,2) DEFAULT NULL,
    
    `status` ENUM('pending', 'active', 'rejected', 'blocked', 'expired') DEFAULT 'pending',
    `expires_at` DATETIME DEFAULT NULL,
    
    `moderated_by` INT NULL,
    `moderated_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    
    `views_count` INT DEFAULT 0,
    `clicks_count` INT DEFAULT 0,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`city_id`) REFERENCES `cities`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`moderated_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_slug` (`slug`),
    INDEX `idx_status_expires` (`status`, `expires_at`),
    INDEX `idx_city_status` (`city_id`, `status`),
    INDEX `idx_created` (`created_at` DESC),
    INDEX `idx_search` (`status`, `city_id`, `age`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 6. ФОТОГРАФИИ АНКЕТ
-- =============================================
CREATE TABLE `profile_photos` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `sort_order` TINYINT DEFAULT 0,
    `is_main` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_profile_main` (`profile_id`, `is_main`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 7. ПЛАТЕЖИ (ЗАЩИЩЕНЫ ОТ КАСКАДНОГО УДАЛЕНИЯ)
-- =============================================
CREATE TABLE `payments` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `plan_id` INT NOT NULL,
    
    `currency` ENUM('BTC', 'ETH', 'USDT') NOT NULL,
    `amount` DECIMAL(16,8) NOT NULL,
    `wallet_address` VARCHAR(255) NOT NULL,
    
    `screenshot` VARCHAR(255) NULL,
    `txid` VARCHAR(255) NULL,
    
    `status` ENUM('waiting', 'confirmed', 'rejected') DEFAULT 'waiting',
    `payment_comment` TEXT NULL,
    `confirmed_by` INT NULL,
    `confirmed_at` DATETIME NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`confirmed_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_profile_status` (`profile_id`, `status`),
    INDEX `idx_status_created` (`status`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 8. НОВОСТИ ИЗ TELEGRAM
-- =============================================
CREATE TABLE `news` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `telegram_post_id` BIGINT UNIQUE NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `image_url` VARCHAR(500) NULL,
    `telegram_url` VARCHAR(500) NULL,
    `published_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_published` (`published_at` DESC),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 9. ПРОСМОТРЫ (СТАТИСТИКА)
-- =============================================
CREATE TABLE `views` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_profile_date` (`profile_id`, `viewed_at` DESC),
    INDEX `idx_date` (`viewed_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 10. ЖАЛОБЫ НА АНКЕТЫ
-- =============================================
CREATE TABLE `complaints` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `profile_id` INT NOT NULL,
    `complaint_text` TEXT NOT NULL,
    `contact` VARCHAR(255) NULL,
    `status` ENUM('new', 'reviewed', 'resolved') DEFAULT 'new',
    `moderator_comment` TEXT NULL,
    `resolved_by` INT NULL,
    `resolved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resolved_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_status_created` (`status`, `created_at` DESC),
    INDEX `idx_profile` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 11. TELEGRAM-БОТ (ЛОГИ ОБРАЩЕНИЙ)
-- =============================================
CREATE TABLE `telegram_messages` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `telegram_user_id` BIGINT NOT NULL,
    `telegram_chat_id` BIGINT NOT NULL,
    `telegram_username` VARCHAR(100) NULL,
    `first_name` VARCHAR(100) NULL,
    `last_name` VARCHAR(100) NULL,
    
    `message_type` ENUM('support', 'exchange', 'complaint', 'other') DEFAULT 'other',
    `message_text` TEXT NOT NULL,
    
    `response_text` TEXT NULL,
    `responded_by` INT NULL,
    `responded_at` DATETIME NULL,
    
    `status` ENUM('new', 'processing', 'resolved', 'closed') DEFAULT 'new',
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`responded_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_user_status` (`telegram_user_id`, `status`),
    INDEX `idx_chat_id` (`telegram_chat_id`),
    INDEX `idx_status_created` (`status`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ГОТОВО! WeToo v3.1
-- 11 таблиц, все связи корректны
-- =============================================