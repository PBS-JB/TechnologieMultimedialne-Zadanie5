SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT 'media/default_avatar.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password`, `avatar`) VALUES
(1, 'admin', 'admin', 'media/default_avatar.png'),
(2, 'test1', 'test1', 'media/default_avatar.png'),
(3, 'test2', 'test2', 'media/default_avatar.png')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

CREATE TABLE IF NOT EXISTS `goscieportalu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ipaddress` VARCHAR(45) NOT NULL,
  `datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `browser` VARCHAR(255) DEFAULT NULL,
  `screen_res` VARCHAR(50) DEFAULT NULL,
  `window_res` VARCHAR(50) DEFAULT NULL,
  `color_depth` VARCHAR(20) DEFAULT NULL,
  `cookies_enabled` VARCHAR(10) DEFAULT NULL,
  `java_enabled` VARCHAR(10) DEFAULT NULL,
  `language` VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `goscieportalu` (`ipaddress`, `datetime`, `browser`, `screen_res`, `window_res`, `color_depth`, `cookies_enabled`, `java_enabled`, `language`) VALUES
('194.29.130.1', NOW() - INTERVAL 10 MINUTE, 'Google Chrome 130', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 8 MINUTE, 'Pomyślne logowanie: test1 [Google Chrome 130]', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 5 MINUTE, 'Mozilla Firefox 131', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL'),
('194.29.130.1', NOW() - INTERVAL 4 MINUTE, 'Pomyślne logowanie: test2 [Mozilla Firefox 131]', '1920x1080', '1920x960', '24 bit', 'Tak', 'Nie', 'pl-PL');

SET FOREIGN_KEY_CHECKS = 1;
