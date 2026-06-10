-- ============================================================
--  LayRate Database Schema
--  Run this in phpMyAdmin or MySQL CLI
--  mysql -u root -p < layrate_schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `layrate`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `layrate`;

-- ─── users ───────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)    NOT NULL,
  `email`      VARCHAR(150)    NOT NULL,
  `password`   VARCHAR(255)    NOT NULL,
  `created_at` TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── cages ───────────────────────────────────────────────────
CREATE TABLE `cages` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `cage_code`  VARCHAR(50)     NOT NULL,
  `location`   VARCHAR(100)    NOT NULL DEFAULT '',
  `capacity`   INT UNSIGNED    NOT NULL DEFAULT 120,
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cages_cage_code_unique` (`cage_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── hens ────────────────────────────────────────────────────
CREATE TABLE `hens` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cage_id`         INT UNSIGNED NOT NULL,
  `tag_code`        VARCHAR(50)  NULL,
  `date_acquired`   DATE         NULL,
  `flock_age_weeks` INT UNSIGNED NOT NULL DEFAULT 0,
  `breed`           ENUM(
                      'ISA Brown',
                      'Lohmann Brown-Classic',
                      'Dekalb White',
                      'Hy-Line Brown',
                      'Novogen Brown'
                    ) NOT NULL DEFAULT 'ISA Brown',
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hens_tag_code_unique` (`tag_code`),
  KEY `hens_cage_id_foreign` (`cage_id`),
  CONSTRAINT `hens_cage_id_foreign`
    FOREIGN KEY (`cage_id`) REFERENCES `cages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── feed_batches ─────────────────────────────────────────────
CREATE TABLE `feed_batches` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `batch_code`     VARCHAR(50)   NOT NULL,
  `crude_protein`  DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `date_received`  DATE          NOT NULL,
  `notes`          TEXT          NULL,
  `created_at`     TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feed_batches_batch_code_unique` (`batch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── production_logs ─────────────────────────────────────────
CREATE TABLE `production_logs` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cage_id`     INT UNSIGNED  NOT NULL,
  `log_date`    DATE          NOT NULL,
  `egg_count`   INT UNSIGNED  NOT NULL DEFAULT 0,
  `hen_count`   INT UNSIGNED  NOT NULL DEFAULT 0,
  `hdep`        DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `recorded_by` INT UNSIGNED  NULL,
  `notes`       TEXT          NULL,
  `created_at`  TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `production_logs_cage_date_unique` (`cage_id`, `log_date`),
  KEY `production_logs_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `production_logs_cage_id_foreign`
    FOREIGN KEY (`cage_id`) REFERENCES `cages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_logs_recorded_by_foreign`
    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── environmental_logs ───────────────────────────────────────
CREATE TABLE `environmental_logs` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cage_id`         INT UNSIGNED NOT NULL,
  `recorded_at`     DATETIME     NOT NULL,
  `temperature_c`   DECIMAL(5,2) NOT NULL,
  `humidity_pct`    DECIMAL(5,2) NOT NULL,
  `created_at`      TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `environmental_logs_cage_id_foreign` (`cage_id`),
  KEY `environmental_logs_recorded_at_index` (`recorded_at`),
  CONSTRAINT `environmental_logs_cage_id_foreign`
    FOREIGN KEY (`cage_id`) REFERENCES `cages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── alerts ──────────────────────────────────────────────────
CREATE TABLE `alerts` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cage_id`      INT UNSIGNED  NOT NULL,
  `alert_type`   VARCHAR(50)   NOT NULL,
  `message`      TEXT          NOT NULL,
  `is_read`      TINYINT(1)    NOT NULL DEFAULT 0,
  `triggered_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`   TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `alerts_cage_id_foreign` (`cage_id`),
  CONSTRAINT `alerts_cage_id_foreign`
    FOREIGN KEY (`cage_id`) REFERENCES `cages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── feed_consumption_logs ────────────────────────────────────
CREATE TABLE `feed_consumption_logs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cage_id`          INT UNSIGNED NOT NULL,
  `feed_batch_id`    INT UNSIGNED NOT NULL,
  `log_date`         DATE         NOT NULL,
  `feed_consumed_kg` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `recorded_by`      INT UNSIGNED NULL,
  `created_at`       TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feed_consumption_logs_cage_date_unique` (`cage_id`, `log_date`),
  KEY `feed_consumption_logs_feed_batch_id_foreign` (`feed_batch_id`),
  KEY `feed_consumption_logs_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `feed_consumption_logs_cage_id_foreign`
    FOREIGN KEY (`cage_id`) REFERENCES `cages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feed_consumption_logs_feed_batch_id_foreign`
    FOREIGN KEY (`feed_batch_id`) REFERENCES `feed_batches` (`id`),
  CONSTRAINT `feed_consumption_logs_recorded_by_foreign`
    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── forecasts ────────────────────────────────────────────────
CREATE TABLE `forecasts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cage_id`        INT UNSIGNED NOT NULL,
  `forecast_date`  DATE         NOT NULL,
  `target_date`    DATE         NOT NULL,
  `predicted_hdep` DECIMAL(5,2) NOT NULL,
  `created_at`     TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `forecasts_cage_id_foreign` (`cage_id`),
  CONSTRAINT `forecasts_cage_id_foreign`
    FOREIGN KEY (`cage_id`) REFERENCES `cages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Laravel framework tables ─────────────────────────────────
CREATE TABLE `cache` (
  `key`        VARCHAR(255) NOT NULL,
  `value`      MEDIUMTEXT   NOT NULL,
  `expiration` INT          NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key`        VARCHAR(255) NOT NULL,
  `owner`      VARCHAR(255) NOT NULL,
  `expiration` INT          NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id`            VARCHAR(255)  NOT NULL,
  `user_id`       BIGINT UNSIGNED NULL,
  `ip_address`    VARCHAR(45)   NULL,
  `user_agent`    TEXT          NULL,
  `payload`       LONGTEXT      NOT NULL,
  `last_activity` INT           NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue`        VARCHAR(255)    NOT NULL,
  `payload`      LONGTEXT        NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL,
  `reserved_at`  INT UNSIGNED    NULL,
  `available_at` INT UNSIGNED    NOT NULL,
  `created_at`   INT UNSIGNED    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       VARCHAR(255)    NOT NULL,
  `connection` TEXT            NOT NULL,
  `queue`      TEXT            NOT NULL,
  `payload`    LONGTEXT        NOT NULL,
  `exception`  LONGTEXT        NOT NULL,
  `failed_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255)  NOT NULL,
  `batch`     INT           NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Sample Data ──────────────────────────────────────────────

-- password = "password" for both accounts
INSERT INTO `users` (`name`, `email`, `role`, `password`) VALUES
('Farm Admin',    'admin@layrate.local',    'admin',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Farm Operator', 'operator@layrate.local', 'operator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO `cages` (`cage_code`, `location`, `capacity`, `is_active`) VALUES
('CAGE-A', 'North Wing', 120, 1),
('CAGE-B', 'East Wing',  120, 1),
('CAGE-C', 'South Wing', 120, 1),
('CAGE-D', 'West Wing',  120, 0);

INSERT INTO `hens` (`cage_id`, `tag_code`, `date_acquired`, `flock_age_weeks`, `breed`, `is_active`) VALUES
(1, 'FLOCK-A-2025', '2025-10-18', 28, 'ISA Brown',             1),
(2, 'FLOCK-B-2025', '2025-09-06', 34, 'Lohmann Brown-Classic', 1),
(3, 'FLOCK-C-2025', '2025-04-19', 52, 'Dekalb White',          1),
(4, 'FLOCK-D-2026', '2025-12-13', 18, 'ISA Brown',             1);

INSERT INTO `feed_batches` (`batch_code`, `crude_protein`, `date_received`, `notes`) VALUES
('F-001', 17.50, '2026-03-01', 'Layer mash - standard'),
('F-002', 16.80, '2026-03-15', 'Layer pellet - supplier B'),
('F-003', 18.00, '2026-03-28', 'Protein-boosted mix');

INSERT INTO `production_logs` (`cage_id`, `log_date`, `egg_count`, `hen_count`, `hdep`, `recorded_by`, `notes`) VALUES
(1, '2026-04-11', 103, 120, 85.83, 1, 'Manual check'),
(2, '2026-04-11',  87, 120, 72.50, 1, 'IR sensor synced'),
(3, '2026-04-11',  70, 120, 58.33, 1, 'Manual check'),
(4, '2026-04-11',   0, 120,  0.00, 1, 'IR sensor synced'),
(1, '2026-04-10', 103, 120, 85.83, 1, 'Manual check'),
(2, '2026-04-10',  87, 120, 72.50, 1, 'IR sensor synced'),
(3, '2026-04-10',  70, 120, 58.33, 1, 'Manual check'),
(4, '2026-04-10',   0, 120,  0.00, 1, 'Manual check'),
(1, '2026-04-09', 103, 120, 85.83, 1, 'IR sensor synced'),
(2, '2026-04-09',  87, 120, 72.50, 1, 'IR sensor synced'),
(3, '2026-04-09',  70, 120, 58.33, 1, 'Manual check'),
(4, '2026-04-09',   0, 120,  0.00, 1, 'Manual check'),
(1, '2026-04-08', 103, 120, 85.83, 1, 'IR sensor synced'),
(2, '2026-04-08',  87, 120, 72.50, 1, 'Manual check'),
(3, '2026-04-08',  70, 120, 58.33, 1, 'Manual check'),
(4, '2026-04-08',   0, 120,  0.00, 1, 'IR sensor synced'),
(1, '2026-04-07', 103, 120, 85.83, 1, 'Manual check'),
(2, '2026-04-07',  87, 120, 72.50, 1, 'IR sensor synced'),
(3, '2026-04-07',  70, 120, 58.33, 1, 'Manual check'),
(4, '2026-04-07',   0, 120,  0.00, 1, 'Manual check'),
(1, '2026-04-06', 100, 120, 83.33, 1, 'IR sensor synced'),
(2, '2026-04-06',  85, 120, 70.83, 1, 'Manual check'),
(3, '2026-04-06',  68, 120, 56.67, 1, 'Manual check'),
(4, '2026-04-06',   0, 120,  0.00, 1, 'Manual check'),
(1, '2026-04-05',  99, 120, 82.50, 1, 'Manual check'),
(2, '2026-04-05',  86, 120, 71.67, 1, 'IR sensor synced'),
(3, '2026-04-05',  69, 120, 57.50, 1, 'Manual check'),
(4, '2026-04-05',   0, 120,  0.00, 1, 'IR sensor synced');

INSERT INTO `environmental_logs` (`cage_id`, `recorded_at`, `temperature_c`, `humidity_pct`) VALUES
(1, '2026-04-11 12:00:00', 28.9, 68.1),
(2, '2026-04-11 12:00:00', 28.7, 70.0),
(3, '2026-04-11 12:00:00', 29.2, 71.0),
(4, '2026-04-11 12:00:00', 27.9, 66.6),
(1, '2026-04-11 10:00:00', 28.5, 68.5),
(2, '2026-04-11 10:00:00', 28.3, 69.5),
(3, '2026-04-11 10:00:00', 28.9, 70.5),
(4, '2026-04-11 10:00:00', 27.6, 66.0),
(1, '2026-04-11 08:00:00', 28.1, 68.8),
(2, '2026-04-11 08:00:00', 27.9, 69.0),
(3, '2026-04-11 08:00:00', 28.5, 70.2),
(4, '2026-04-11 08:00:00', 27.3, 65.5),
(1, '2026-04-11 06:00:00', 27.5, 69.2),
(2, '2026-04-11 06:00:00', 27.2, 68.8),
(3, '2026-04-11 06:00:00', 28.0, 70.8),
(4, '2026-04-11 06:00:00', 27.0, 65.0),
(1, '2026-04-11 04:00:00', 27.3, 70.8),
(2, '2026-04-11 04:00:00', 27.0, 70.0),
(3, '2026-04-11 04:00:00', 27.7, 71.5),
(4, '2026-04-11 04:00:00', 26.8, 65.2),
(1, '2026-04-11 02:00:00', 27.4, 69.2),
(2, '2026-04-11 02:00:00', 27.1, 69.5),
(3, '2026-04-11 02:00:00', 27.6, 71.0),
(4, '2026-04-11 02:00:00', 26.5, 64.8),
(1, '2026-04-11 00:00:00', 27.4, 68.0),
(2, '2026-04-11 00:00:00', 27.0, 68.5),
(3, '2026-04-11 00:00:00', 27.8, 70.5),
(4, '2026-04-11 00:00:00', 26.3, 64.5),
(1, '2026-04-10 22:00:00', 27.7, 68.1),
(2, '2026-04-10 22:00:00', 27.1, 68.0),
(3, '2026-04-10 22:00:00', 28.2, 70.5),
(4, '2026-04-10 22:00:00', 26.6, 64.2),
(1, '2026-04-10 20:00:00', 28.2, 68.0),
(2, '2026-04-10 20:00:00', 27.5, 67.8),
(3, '2026-04-10 20:00:00', 28.6, 70.2),
(4, '2026-04-10 20:00:00', 27.0, 64.0),
(1, '2026-04-10 18:00:00', 28.7, 68.9),
(2, '2026-04-10 18:00:00', 27.9, 70.5),
(3, '2026-04-10 18:00:00', 28.8, 68.9),
(4, '2026-04-10 18:00:00', 27.9, 66.6),
(1, '2026-04-10 16:00:00', 28.8, 70.5),
(2, '2026-04-10 16:00:00', 28.0, 70.5),
(3, '2026-04-10 16:00:00', 29.0, 70.0),
(4, '2026-04-10 16:00:00', 27.5, 66.0),
(1, '2026-04-10 14:00:00', 28.6, 72.0),
(2, '2026-04-10 14:00:00', 27.2, 70.8),
(3, '2026-04-10 14:00:00', 28.0, 72.5),
(4, '2026-04-10 14:00:00', 27.2, 65.5);

INSERT INTO `feed_consumption_logs` (`cage_id`, `feed_batch_id`, `log_date`, `feed_consumed_kg`, `recorded_by`) VALUES
(1, 3, '2026-04-11', 11.4, 1),
(2, 3, '2026-04-11', 12.4, 1),
(3, 3, '2026-04-11', 13.4, 1),
(1, 3, '2026-04-10', 12.0, 1),
(2, 3, '2026-04-10', 11.8, 1),
(3, 3, '2026-04-10', 12.5, 1),
(1, 2, '2026-04-09', 11.5, 1),
(2, 2, '2026-04-09', 12.0, 1),
(3, 2, '2026-04-09', 13.0, 1),
(1, 2, '2026-04-08', 11.8, 1),
(2, 2, '2026-04-08', 12.2, 1),
(3, 2, '2026-04-08', 13.2, 1),
(1, 1, '2026-04-07', 11.4, 1),
(2, 1, '2026-04-07', 12.4, 1),
(3, 1, '2026-04-07', 13.4, 1),
(1, 1, '2026-04-06', 11.6, 1),
(2, 1, '2026-04-06', 12.1, 1),
(3, 1, '2026-04-06', 13.1, 1),
(1, 1, '2026-04-05', 11.3, 1),
(2, 1, '2026-04-05', 12.3, 1),
(3, 1, '2026-04-05', 13.3, 1);

INSERT INTO `alerts` (`cage_id`, `alert_type`, `message`, `is_read`, `triggered_at`) VALUES
(3, 'humidity_high',  'Humidity at 71% — above 70% threshold', 0, '2026-04-11 14:00:00'),
(2, 'humidity_watch', 'Humidity at 70% — at threshold boundary',    0, '2026-04-11 10:30:00');

INSERT INTO `forecasts` (`cage_id`, `forecast_date`, `target_date`, `predicted_hdep`) VALUES
(1, '2026-04-11', '2026-04-12', 86.10),
(1, '2026-04-11', '2026-04-13', 86.40),
(1, '2026-04-11', '2026-04-14', 85.90),
(1, '2026-04-11', '2026-04-15', 86.20),
(1, '2026-04-11', '2026-04-16', 85.70),
(1, '2026-04-11', '2026-04-17', 85.50),
(1, '2026-04-11', '2026-04-18', 86.00);

-- ─── Phase 1 additions (run these if importing into existing DB) ──

-- Add role column to users
ALTER TABLE `users`
  ADD COLUMN `role` ENUM('admin','operator') NOT NULL DEFAULT 'operator'
  AFTER `email`;

-- ─── mortality_logs ───────────────────────────────────────────
CREATE TABLE `mortality_logs` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `cage_id`     INT UNSIGNED  NOT NULL,
  `log_date`    DATE          NOT NULL,
  `count`       INT UNSIGNED  NOT NULL DEFAULT 1,
  `reason`      ENUM('Disease','Heat Stress','Injury','Predator','Unknown','Other')
                              NOT NULL DEFAULT 'Unknown',
  `notes`       TEXT          NULL,
  `recorded_by` INT UNSIGNED  NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mortality_logs_cage_id_foreign` (`cage_id`),
  KEY `mortality_logs_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `mortality_logs_cage_id_foreign`
    FOREIGN KEY (`cage_id`) REFERENCES `cages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mortality_logs_recorded_by_foreign`
    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── settings ─────────────────────────────────────────────────
CREATE TABLE `settings` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(100)  NOT NULL,
  `value`      VARCHAR(255)  NULL,
  `label`      VARCHAR(150)  NULL,
  `created_at` TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`, `label`) VALUES
('temp_min', '18', 'Temperature Minimum (°C)'),
('temp_max', '30', 'Temperature Maximum (°C)'),
('hum_min',  '40', 'Humidity Minimum (%)'),
('hum_max',  '70', 'Humidity Maximum (%)');

-- ─── Sample mortality data ─────────────────────────────────────
INSERT INTO `mortality_logs` (`cage_id`, `log_date`, `count`, `reason`, `notes`, `recorded_by`) VALUES
(3, '2026-06-09', 1, 'Heat Stress', 'Found near water trough, high temp recorded that day', 1),
(1, '2026-06-08', 1, 'Unknown',     NULL, 1),
(3, '2026-06-07', 2, 'Disease',     'Respiratory symptoms observed in surrounding hens', 1),
(2, '2026-06-06', 1, 'Injury',      'Likely pecking injury — isolated others', 1),
(3, '2026-06-04', 1, 'Disease',     NULL, 1),
(1, '2026-06-02', 1, 'Unknown',     NULL, 1),
(4, '2026-05-31', 1, 'Other',       'Cage D flock still in low-production phase', 1);
