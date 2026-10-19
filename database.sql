-- ========================================================
-- Database Schema for Rubik Timer (csTimer Clone)
-- Target Database: rubik_timer
-- Host: 127.0.0.1:3306 (Laragon MySQL)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `rubik_timer` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `rubik_timer`;

CREATE TABLE IF NOT EXISTS `solves` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `raw_time_ms` INT UNSIGNED NOT NULL COMMENT 'Durasi murni stopwatch dalam milidetik (misal 14250 = 14.25 detik)',
    `scramble` VARCHAR(255) NOT NULL COMMENT 'Algoritma scramble 20 langkah standar WCA',
    `penalty` ENUM('OK', '+2', 'DNF') NOT NULL DEFAULT 'OK' COMMENT 'Status penalti: OK, +2 (+2000ms), atau DNF',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu pencatatan solve',
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_penalty` (`penalty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
