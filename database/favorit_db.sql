-- ============================================================================
--  Информационная система «Аналитика эффективности предприятия» ООО «ФАВОРИТ»
--  Курсовая работа, ПМ.05 Проектирование и разработка информационных систем
--  Студент: Семиглазов Данил Вячеславович, группа 44 СПО
--
--  Файл создаёт базу данных favorit_db со всеми таблицами и демонстрационными
--  данными за май–октябрь 2025 года.
--
--  Импорт: OpenServer → phpMyAdmin → выбрать сервер → вкладка «Импорт» →
--  выбрать этот файл → кодировка utf-8 → «Вперёд».
--  Либо из консоли:  mysql -u root < database/favorit_db.sql
--
--  Пароль администратора: manager / manager123 (в базе хранится bcrypt-хеш).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `favorit_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `favorit_db`;

-- ----------------------------------------------------------------------------
--  СТРУКТУРА ТАБЛИЦ
-- ----------------------------------------------------------------------------

-- Таблица `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL DEFAULT '',
  `phone` VARCHAR(30) NOT NULL DEFAULT '',
  `email` VARCHAR(100) NOT NULL DEFAULT '',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `role` VARCHAR(20) NOT NULL DEFAULT 'manager',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_categories`
DROP TABLE IF EXISTS `an_categories`;
CREATE TABLE IF NOT EXISTS `an_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_products`
DROP TABLE IF EXISTS `an_products`;
CREATE TABLE IF NOT EXISTS `an_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'шт.',
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `active` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
      REFERENCES `an_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_channels`
DROP TABLE IF EXISTS `an_channels`;
CREATE TABLE IF NOT EXISTS `an_channels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `type` VARCHAR(40) NOT NULL DEFAULT 'прямые',
  `plan_revenue` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `leads` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_production`
DROP TABLE IF EXISTS `an_production`;
CREATE TABLE IF NOT EXISTS `an_production` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period` VARCHAR(7) NOT NULL COMMENT 'Месяц в формате ГГГГ-ММ',
  `workshop` VARCHAR(80) NOT NULL,
  `category` VARCHAR(60) NOT NULL,
  `qty` INT NOT NULL DEFAULT 0,
  `defects` INT NOT NULL DEFAULT 0,
  `hours` DECIMAL(10,1) NOT NULL DEFAULT 0.0,
  `cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_production_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_sales`
DROP TABLE IF EXISTS `an_sales`;
CREATE TABLE IF NOT EXISTS `an_sales` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period` VARCHAR(7) NOT NULL,
  `channel` VARCHAR(60) NOT NULL,
  `orders` INT NOT NULL DEFAULT 0,
  `revenue` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_sales_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_orders`
DROP TABLE IF EXISTS `an_orders`;
CREATE TABLE IF NOT EXISTS `an_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period` VARCHAR(7) NOT NULL,
  `client` VARCHAR(120) NOT NULL,
  `product` VARCHAR(120) NOT NULL,
  `channel` VARCHAR(80) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'new',
  `due_date` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orders_period` (`period`),
  KEY `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_defects`
DROP TABLE IF EXISTS `an_defects`;
CREATE TABLE IF NOT EXISTS `an_defects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period` VARCHAR(7) NOT NULL,
  `workshop` VARCHAR(80) NOT NULL,
  `reason` VARCHAR(80) NOT NULL,
  `qty` INT NOT NULL DEFAULT 0,
  `cost` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `idx_defects_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_staff`
DROP TABLE IF EXISTS `an_staff`;
CREATE TABLE IF NOT EXISTS `an_staff` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(120) NOT NULL,
  `department` VARCHAR(80) NOT NULL,
  `position` VARCHAR(80) NOT NULL,
  `salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `hired_at` VARCHAR(10) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_staff_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_targets`
DROP TABLE IF EXISTS `an_targets`;
CREATE TABLE IF NOT EXISTS `an_targets` (
  `metric` VARCHAR(40) NOT NULL,
  `target_value` DECIMAL(14,2) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`metric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица `an_audit`
DROP TABLE IF EXISTS `an_audit`;
CREATE TABLE IF NOT EXISTS `an_audit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor` VARCHAR(120) NOT NULL,
  `action` VARCHAR(120) NOT NULL,
  `details` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` VARCHAR(19) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
--  ДЕМОНСТРАЦИОННЫЕ ДАННЫЕ (май–октябрь 2025)
-- ----------------------------------------------------------------------------

-- users: 1 записей
INSERT INTO `users` (`id`, `login`, `password`, `full_name`, `phone`, `email`, `created_at`, `role`) VALUES
(1, 'manager', '$2y$10$4yfeTSY7aXV2dQPY0sFAEOhsadVqljrUVm9PUbdhx2uiXkwzMKLCe', 'Менеджер Фаворит', '', 'manager@favorit.local', '2026-11-17 12:06:44', 'admin');

-- an_categories: 3 записей
INSERT INTO `an_categories` (`id`, `name`) VALUES
(1, 'Диваны'),
(2, 'Шкафы и столы'),
(3, 'Комплектующие');

-- an_products: 7 записей
INSERT INTO `an_products` (`id`, `category_id`, `title`, `unit`, `price`, `active`) VALUES
(1, 1, 'Диван «Модерн»', 'шт.', 68000, 1),
(2, 1, 'Диван «Осло»', 'шт.', 74000, 1),
(3, 1, 'Кресло «Лира»', 'шт.', 32000, 1),
(4, 2, 'Шкаф «Классик»', 'шт.', 41000, 1),
(5, 2, 'Стол письменный', 'шт.', 28000, 1),
(6, 3, 'Комплект каркаса', 'компл.', 7800, 1),
(7, 3, 'Обивка', 'м²', 1450, 1);

-- an_channels: 4 записей
INSERT INTO `an_channels` (`id`, `name`, `type`, `plan_revenue`, `cost`, `leads`) VALUES
(1, 'Сайт', 'онлайн', 4000000, 120000, 820),
(2, 'Маркетплейсы', 'маркетплейс', 2300000, 210000, 1450),
(3, 'Оптовые партнёры', 'B2B', 1700000, 90000, 210),
(4, 'Розница', 'офлайн', 700000, 45000, 120);

-- an_production: 18 записей
INSERT INTO `an_production` (`id`, `period`, `workshop`, `category`, `qty`, `defects`, `hours`, `cost`) VALUES
(1, '2025-05', 'Цех мягкой мебели', 'Диваны', 5460, 142, 2350, 3494400),
(2, '2025-05', 'Цех корпусной мебели', 'Шкафы и столы', 3255, 85, 1450, 1953000),
(3, '2025-05', 'Раскройный участок', 'Комплектующие', 1785, 46, 900, 1035300),
(4, '2025-06', 'Цех мягкой мебели', 'Диваны', 5772, 144, 2350, 3694080),
(5, '2025-06', 'Цех корпусной мебели', 'Шкафы и столы', 3441, 86, 1450, 2064600),
(6, '2025-06', 'Раскройный участок', 'Комплектующие', 1887, 47, 900, 1094460),
(7, '2025-07', 'Цех мягкой мебели', 'Диваны', 6344, 152, 2350, 4060160),
(8, '2025-07', 'Цех корпусной мебели', 'Шкафы и столы', 3782, 91, 1450, 2269200),
(9, '2025-07', 'Раскройный участок', 'Комплектующие', 2074, 50, 900, 1202920),
(10, '2025-08', 'Цех мягкой мебели', 'Диваны', 6500, 150, 2350, 4160000),
(11, '2025-08', 'Цех корпусной мебели', 'Шкафы и столы', 3875, 89, 1450, 2325000),
(12, '2025-08', 'Раскройный участок', 'Комплектующие', 2125, 49, 900, 1232500),
(13, '2025-09', 'Цех мягкой мебели', 'Диваны', 6552, 157, 2350, 4193280),
(14, '2025-09', 'Цех корпусной мебели', 'Шкафы и столы', 3906, 94, 1450, 2343600),
(15, '2025-09', 'Раскройный участок', 'Комплектующие', 2142, 51, 900, 1242360),
(16, '2025-10', 'Цех мягкой мебели', 'Диваны', 6490, 156, 2350, 4153600),
(17, '2025-10', 'Цех корпусной мебели', 'Шкафы и столы', 3869, 93, 1450, 2321400),
(18, '2025-10', 'Раскройный участок', 'Комплектующие', 2121, 51, 900, 1230180);

-- an_sales: 24 записей
INSERT INTO `an_sales` (`id`, `period`, `channel`, `orders`, `revenue`) VALUES
(1, '2025-05', 'Сайт', 146, 3634000),
(2, '2025-05', 'Маркетплейсы', 86, 2133000),
(3, '2025-05', 'Оптовые партнёры', 60, 1501000),
(4, '2025-05', 'Розница', 25, 632000),
(5, '2025-06', 'Сайт', 148, 3703000),
(6, '2025-06', 'Маркетплейсы', 87, 2173500),
(7, '2025-06', 'Оптовые партнёры', 61, 1529500),
(8, '2025-06', 'Розница', 26, 644000),
(9, '2025-07', 'Сайт', 157, 3776600),
(10, '2025-07', 'Маркетплейсы', 92, 2216700),
(11, '2025-07', 'Оптовые партнёры', 65, 1559900),
(12, '2025-07', 'Розница', 27, 656800),
(13, '2025-08', 'Сайт', 162, 3831800),
(14, '2025-08', 'Маркетплейсы', 95, 2249100),
(15, '2025-08', 'Оптовые партнёры', 67, 1582700),
(16, '2025-08', 'Розница', 28, 666400),
(17, '2025-09', 'Сайт', 166, 3891600),
(18, '2025-09', 'Маркетплейсы', 97, 2284200),
(19, '2025-09', 'Оптовые партнёры', 69, 1607400),
(20, '2025-09', 'Розница', 29, 676800),
(21, '2025-10', 'Сайт', 169, 3928400),
(22, '2025-10', 'Маркетплейсы', 99, 2305800),
(23, '2025-10', 'Оптовые партнёры', 70, 1622600),
(24, '2025-10', 'Розница', 29, 683200);

-- an_orders: 48 записей
INSERT INTO `an_orders` (`id`, `period`, `client`, `product`, `channel`, `amount`, `status`, `due_date`) VALUES
(1, '2025-05', 'ООО «Дом-Стиль»', 'Диван «Модерн»', 'Сайт', 35000, 'new', '2025-05-10'),
(2, '2025-05', 'ИП Сергеев', 'Диван «Осло»', 'Маркетплейсы', 67500, 'in_progress', '2025-05-13'),
(3, '2025-05', 'ООО «Мебель Плюс»', 'Шкаф «Классик»', 'Оптовые партнёры', 50000, 'done', '2025-05-16'),
(4, '2025-05', 'ИП Ковалёва', 'Стол письменный', 'Розница', 82500, 'done', '2025-05-19'),
(5, '2025-05', 'ООО «Офис-Диск»', 'Кресло «Лира»', 'Сайт', 65000, 'done', '2025-05-22'),
(6, '2025-05', 'ООО «Уют»', 'Диван «Модерн»', 'Маркетплейсы', 47500, 'cancelled', '2025-05-25'),
(7, '2025-05', 'ООО «Дом-Стиль»', 'Диван «Осло»', 'Оптовые партнёры', 80000, 'new', '2025-05-28'),
(8, '2025-05', 'ИП Сергеев', 'Шкаф «Классик»', 'Розница', 62500, 'in_progress', '2025-05-31'),
(9, '2025-06', 'ИП Сергеев', 'Диван «Осло»', 'Маркетплейсы', 52500, 'in_progress', '2025-06-10'),
(10, '2025-06', 'ООО «Мебель Плюс»', 'Шкаф «Классик»', 'Оптовые партнёры', 35000, 'done', '2025-06-13'),
(11, '2025-06', 'ИП Ковалёва', 'Стол письменный', 'Розница', 67500, 'done', '2025-06-16'),
(12, '2025-06', 'ООО «Офис-Диск»', 'Кресло «Лира»', 'Сайт', 50000, 'done', '2025-06-19'),
(13, '2025-06', 'ООО «Уют»', 'Диван «Модерн»', 'Маркетплейсы', 82500, 'cancelled', '2025-06-22'),
(14, '2025-06', 'ООО «Дом-Стиль»', 'Диван «Осло»', 'Оптовые партнёры', 65000, 'new', '2025-06-25'),
(15, '2025-06', 'ИП Сергеев', 'Шкаф «Классик»', 'Розница', 47500, 'in_progress', '2025-06-28'),
(16, '2025-06', 'ООО «Мебель Плюс»', 'Стол письменный', 'Сайт', 80000, 'done', '2025-07-01'),
(17, '2025-07', 'ООО «Мебель Плюс»', 'Шкаф «Классик»', 'Оптовые партнёры', 70000, 'done', '2025-07-10'),
(18, '2025-07', 'ИП Ковалёва', 'Стол письменный', 'Розница', 52500, 'done', '2025-07-13'),
(19, '2025-07', 'ООО «Офис-Диск»', 'Кресло «Лира»', 'Сайт', 35000, 'done', '2025-07-16'),
(20, '2025-07', 'ООО «Уют»', 'Диван «Модерн»', 'Маркетплейсы', 67500, 'cancelled', '2025-07-19'),
(21, '2025-07', 'ООО «Дом-Стиль»', 'Диван «Осло»', 'Оптовые партнёры', 50000, 'new', '2025-07-22'),
(22, '2025-07', 'ИП Сергеев', 'Шкаф «Классик»', 'Розница', 82500, 'in_progress', '2025-07-25'),
(23, '2025-07', 'ООО «Мебель Плюс»', 'Стол письменный', 'Сайт', 65000, 'done', '2025-07-28'),
(24, '2025-07', 'ИП Ковалёва', 'Кресло «Лира»', 'Маркетплейсы', 47500, 'done', '2025-07-31'),
(25, '2025-08', 'ИП Ковалёва', 'Стол письменный', 'Розница', 37500, 'done', '2025-08-10'),
(26, '2025-08', 'ООО «Офис-Диск»', 'Кресло «Лира»', 'Сайт', 70000, 'done', '2025-08-13'),
(27, '2025-08', 'ООО «Уют»', 'Диван «Модерн»', 'Маркетплейсы', 52500, 'cancelled', '2025-08-16'),
(28, '2025-08', 'ООО «Дом-Стиль»', 'Диван «Осло»', 'Оптовые партнёры', 35000, 'new', '2025-08-19'),
(29, '2025-08', 'ИП Сергеев', 'Шкаф «Классик»', 'Розница', 67500, 'in_progress', '2025-08-22'),
(30, '2025-08', 'ООО «Мебель Плюс»', 'Стол письменный', 'Сайт', 50000, 'done', '2025-08-25'),
(31, '2025-08', 'ИП Ковалёва', 'Кресло «Лира»', 'Маркетплейсы', 82500, 'done', '2025-08-28'),
(32, '2025-08', 'ООО «Офис-Диск»', 'Диван «Модерн»', 'Оптовые партнёры', 65000, 'done', '2025-08-31'),
(33, '2025-09', 'ООО «Офис-Диск»', 'Кресло «Лира»', 'Сайт', 55000, 'done', '2025-09-10'),
(34, '2025-09', 'ООО «Уют»', 'Диван «Модерн»', 'Маркетплейсы', 37500, 'cancelled', '2025-09-13'),
(35, '2025-09', 'ООО «Дом-Стиль»', 'Диван «Осло»', 'Оптовые партнёры', 70000, 'new', '2025-09-16'),
(36, '2025-09', 'ИП Сергеев', 'Шкаф «Классик»', 'Розница', 52500, 'in_progress', '2025-09-19'),
(37, '2025-09', 'ООО «Мебель Плюс»', 'Стол письменный', 'Сайт', 35000, 'done', '2025-09-22'),
(38, '2025-09', 'ИП Ковалёва', 'Кресло «Лира»', 'Маркетплейсы', 67500, 'done', '2025-09-25'),
(39, '2025-09', 'ООО «Офис-Диск»', 'Диван «Модерн»', 'Оптовые партнёры', 50000, 'done', '2025-09-28'),
(40, '2025-09', 'ООО «Уют»', 'Диван «Осло»', 'Розница', 82500, 'cancelled', '2025-10-01'),
(41, '2025-10', 'ООО «Уют»', 'Диван «Модерн»', 'Маркетплейсы', 72500, 'cancelled', '2025-10-10'),
(42, '2025-10', 'ООО «Дом-Стиль»', 'Диван «Осло»', 'Оптовые партнёры', 55000, 'new', '2025-10-13'),
(43, '2025-10', 'ИП Сергеев', 'Шкаф «Классик»', 'Розница', 37500, 'in_progress', '2025-10-16'),
(44, '2025-10', 'ООО «Мебель Плюс»', 'Стол письменный', 'Сайт', 70000, 'done', '2025-10-19'),
(45, '2025-10', 'ИП Ковалёва', 'Кресло «Лира»', 'Маркетплейсы', 52500, 'done', '2025-10-22'),
(46, '2025-10', 'ООО «Офис-Диск»', 'Диван «Модерн»', 'Оптовые партнёры', 35000, 'done', '2025-10-25'),
(47, '2025-10', 'ООО «Уют»', 'Диван «Осло»', 'Розница', 67500, 'cancelled', '2025-10-28'),
(48, '2025-10', 'ООО «Дом-Стиль»', 'Шкаф «Классик»', 'Сайт', 50000, 'new', '2025-10-31');

-- an_defects: 24 записей
INSERT INTO `an_defects` (`id`, `period`, `workshop`, `reason`, `qty`, `cost`, `status`) VALUES
(1, '2025-05', 'Цех мягкой мебели', 'Оборудование', 18, 75600, 'open'),
(2, '2025-05', 'Цех корпусной мебели', 'Материал', 27, 135000, 'closed'),
(3, '2025-05', 'Раскройный участок', 'Человеческий фактор', 36, 208800, 'closed'),
(4, '2025-05', 'Цех мягкой мебели', 'Логистика', 45, 297000, 'closed'),
(5, '2025-06', 'Цех корпусной мебели', 'Оборудование', 23, 96600, 'closed'),
(6, '2025-06', 'Раскройный участок', 'Материал', 32, 160000, 'closed'),
(7, '2025-06', 'Цех мягкой мебели', 'Человеческий фактор', 41, 237800, 'closed'),
(8, '2025-06', 'Цех корпусной мебели', 'Логистика', 20, 132000, 'closed'),
(9, '2025-07', 'Раскройный участок', 'Оборудование', 28, 117600, 'open'),
(10, '2025-07', 'Цех мягкой мебели', 'Материал', 37, 185000, 'closed'),
(11, '2025-07', 'Цех корпусной мебели', 'Человеческий фактор', 46, 266800, 'closed'),
(12, '2025-07', 'Раскройный участок', 'Логистика', 25, 165000, 'closed'),
(13, '2025-08', 'Цех мягкой мебели', 'Оборудование', 33, 138600, 'closed'),
(14, '2025-08', 'Цех корпусной мебели', 'Материал', 42, 210000, 'closed'),
(15, '2025-08', 'Раскройный участок', 'Человеческий фактор', 21, 121800, 'closed'),
(16, '2025-08', 'Цех мягкой мебели', 'Логистика', 30, 198000, 'closed'),
(17, '2025-09', 'Цех корпусной мебели', 'Оборудование', 38, 159600, 'open'),
(18, '2025-09', 'Раскройный участок', 'Материал', 47, 235000, 'closed'),
(19, '2025-09', 'Цех мягкой мебели', 'Человеческий фактор', 26, 150800, 'closed'),
(20, '2025-09', 'Цех корпусной мебели', 'Логистика', 35, 231000, 'closed'),
(21, '2025-10', 'Раскройный участок', 'Оборудование', 43, 180600, 'closed'),
(22, '2025-10', 'Цех мягкой мебели', 'Материал', 22, 110000, 'closed'),
(23, '2025-10', 'Цех корпусной мебели', 'Человеческий фактор', 31, 179800, 'closed'),
(24, '2025-10', 'Раскройный участок', 'Логистика', 40, 264000, 'closed');

-- an_staff: 10 записей
INSERT INTO `an_staff` (`id`, `full_name`, `department`, `position`, `salary`, `hired_at`, `status`) VALUES
(1, 'Иванов И. И.', 'Производство', 'Начальник цеха', 92000, '2019-03-12', 'active'),
(2, 'Петрова А. С.', 'Производство', 'Мастер участка', 71000, '2021-07-01', 'active'),
(3, 'Сидоров П. А.', 'Производство', 'Оператор ЧПУ', 64000, '2022-02-14', 'active'),
(4, 'Кузнецов Д. В.', 'Производство', 'Сборщик мебели', 58000, '2023-05-22', 'active'),
(5, 'Смирнова Е. А.', 'Финансы', 'Главный бухгалтер', 98000, '2018-11-05', 'active'),
(6, 'Волкова М. И.', 'Финансы', 'Экономист', 72000, '2022-09-19', 'active'),
(7, 'Морозов А. Ю.', 'Продажи', 'Руководитель отдела', 85000, '2020-01-20', 'active'),
(8, 'Фёдорова Н. В.', 'Продажи', 'Менеджер по продажам', 56000, '2023-04-03', 'active'),
(9, 'Соколов Р. Н.', 'Маркетинг', 'Маркетолог', 69000, '2022-06-15', 'active'),
(10, 'Лебедева О. К.', 'Кадры', 'HR-специалист', 61000, '2021-10-11', 'active');

-- an_targets: 3 записей
INSERT INTO `an_targets` (`metric`, `target_value`, `title`) VALUES
('capacity', 5400, 'Плановая загрузка мощностей, ч/мес'),
('defect_max', 3, 'Допустимый брак, %'),
('revenue_plan', 8500000, 'План выручки, ₽/мес');

SET FOREIGN_KEY_CHECKS = 1;

-- Конец файла. Проверка: SELECT COUNT(*) FROM an_production;  -- 18 записей
