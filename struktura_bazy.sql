SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";


CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Login użytkownika (nazwa katalogu macierzystego)',
  `password` VARCHAR(255) NOT NULL COMMENT 'Hasło użytkownika (tekstowe / haszowane)',
  `avatar` VARCHAR(255) DEFAULT 'media/default_avatar.png' COMMENT 'Ścieżka do awatara użytkownika'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `users` (`id`, `username`, `password`, `avatar`) VALUES
(1, 'admin', 'admin', 'media/default_avatar.png'),
(2, 'test1', 'test1', 'media/default_avatar.png'),
(3, 'test2', 'test2', 'media/default_avatar.png')
ON DUPLICATE KEY UPDATE 
  `password` = VALUES(`password`),
  `avatar` = VALUES(`avatar`);


CREATE TABLE IF NOT EXISTS `goscieportalu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ipaddress` VARCHAR(45) NOT NULL COMMENT 'Adres IPv4 lub IPv6 gościa portalu',
  `datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data i godzina zdarzenia',
  `browser` VARCHAR(255) DEFAULT NULL COMMENT 'Nazwa i wersja przeglądarki / informacja o zdarzeniu logowania',
  `screen_res` VARCHAR(50) DEFAULT NULL COMMENT 'Rozdzielczość ekranu klienta (np. 1920x1080)',
  `window_res` VARCHAR(50) DEFAULT NULL COMMENT 'Rozdzielczość okna przeglądarki (np. 1920x960)',
  `color_depth` VARCHAR(20) DEFAULT NULL COMMENT 'Głębia kolorów ekranu (np. 24 bit)',
  `cookies_enabled` VARCHAR(10) DEFAULT NULL COMMENT 'Obsługa ciasteczek (Tak/Nie)',
  `java_enabled` VARCHAR(10) DEFAULT NULL COMMENT 'Obsługa apletów Java (Tak/Nie)',
  `language` VARCHAR(50) DEFAULT NULL COMMENT 'Język preferowany przeglądarki'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `goscieportalu` (`ipaddress`, `datetime`, `browser`, `screen_res`, `window_res`, `color_depth`, `cookies_enabled`, `java_enabled`, `language`) VALUES
('194.29.130.1', NOW() - INTERVAL 15 MINUTE, 'Google Chrome 130', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 12 MINUTE, 'Pomyślne logowanie: admin [Google Chrome 130]', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 8 MINUTE, 'Pomyślne logowanie: test1 [Google Chrome 130]', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 5 MINUTE, 'Mozilla Firefox 131', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 3 MINUTE, 'Pomyślne logowanie: test2 [Mozilla Firefox 131]', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL');


CREATE TABLE IF NOT EXISTS `break_ins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ipaddress` VARCHAR(45) NOT NULL COMMENT 'Adres IP komputera, z którego nastąpiła próba',
  `datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data i czas incydentu',
  `username` VARCHAR(50) DEFAULT NULL COMMENT 'Login konta, na które usiłowano się zalogować',
  `attempts` INT DEFAULT 3 COMMENT 'Liczba kolejnych błędnych prób',
  INDEX `idx_break_ins_ip` (`ipaddress`),
  INDEX `idx_break_ins_datetime` (`datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
