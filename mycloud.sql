-- ==============================================================================
-- Baza danych dla Zadania 5: MyCloud (Dysk Sieciowy)
-- Przedmiot: Technologie Multimedialne
-- Kierunek: Informatyka Stosowana, Politechnika Bydgoska
-- Prowadzący: dr inż. Piotr Grad
-- Autor: Jakub Baka
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- Tabela 1: `users` - Konta użytkowników dysku sieciowego MyCloud
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Login użytkownika (nazwa katalogu domowego)',
  `password` VARCHAR(255) NOT NULL COMMENT 'Hasło użytkownika',
  `avatar` VARCHAR(255) DEFAULT 'media/default_avatar.png' COMMENT 'Ścieżka do awatara'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Przykładowe konta użytkowników wykorzystywane do testów izolacji danych (Pkt 6.c, 7.a, 7.b)
INSERT INTO `users` (`id`, `username`, `password`, `avatar`) VALUES
(1, 'admin', 'admin', 'media/default_avatar.png'),
(2, 'test1', 'test1', 'media/default_avatar.png'),
(3, 'test2', 'test2', 'media/default_avatar.png')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- ------------------------------------------------------------------------------
-- Tabela 2: `goscieportalu` - Rejestracja wizyt i logowań (dziedziczona z Z3/Z4)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `goscieportalu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ipaddress` VARCHAR(45) NOT NULL COMMENT 'Adres IP gościa portalu',
  `datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data i czas wizyty / logowania',
  `browser` VARCHAR(255) DEFAULT NULL COMMENT 'Przefiltrowana nazwa i wersja przeglądarki',
  `screen_res` VARCHAR(50) DEFAULT NULL COMMENT 'Rozdzielczość ekranu klienta',
  `window_res` VARCHAR(50) DEFAULT NULL COMMENT 'Rozdzielczość okna przeglądarki',
  `color_depth` VARCHAR(20) DEFAULT NULL COMMENT 'Głębia kolorów ekranu bity/px',
  `cookies_enabled` VARCHAR(10) DEFAULT NULL COMMENT 'Zezwolenie na ciasteczka Tak/Nie',
  `java_enabled` VARCHAR(10) DEFAULT NULL COMMENT 'Zezwolenie na aplety Java Tak/Nie',
  `language` VARCHAR(50) DEFAULT NULL COMMENT 'Język przeglądarki'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Przykładowe wpisy logowania i wizyt (Pkt 6.b)
INSERT INTO `goscieportalu` (`ipaddress`, `datetime`, `browser`, `screen_res`, `window_res`, `color_depth`, `cookies_enabled`, `java_enabled`, `language`) VALUES
('194.29.130.1', NOW() - INTERVAL 10 MINUTE, 'Google Chrome 130', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 8 MINUTE, 'Pomyślne logowanie: test1 [Google Chrome 130]', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 5 MINUTE, 'Mozilla Firefox 131', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 4 MINUTE, 'Pomyślne logowanie: test2 [Mozilla Firefox 131]', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL');

SET FOREIGN_KEY_CHECKS = 1;
