<?php
declare(strict_types=1);

function getDbConnection(): mysqli {
    $host = 'mysql.mikr.us';
    $user = 'viktor229';
    $pass = '50C5_029726';
    $dbname = 'db_viktor229';

    $link = mysqli_connect($host, $user, $pass, $dbname);

    if (!$link) {
        die("Błąd połączenia z bazą danych: " . mysqli_connect_error());
    }

    mysqli_query($link, "SET NAMES 'utf8mb4'");
    return $link;
}

function get_client_ip(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', $_SERVER[$header]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function get_filtered_browser(?string $userAgent = null): string {
    $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Nieznana');

    if (ini_get('browscap') && function_exists('get_browser')) {
        $info = @get_browser($userAgent, true);
        if (!empty($info['browser'])) {
            $ver = !empty($info['version']) ? ' ' . $info['version'] : '';
            return trim($info['browser'] . $ver);
        }
    }

    if (preg_match('/Edge?\/([0-9.]+)/i', $userAgent, $m)) {
        return 'Microsoft Edge ' . explode('.', $m[1])[0];
    } elseif (preg_match('/OPR\/([0-9.]+)/i', $userAgent, $m)) {
        return 'Opera ' . explode('.', $m[1])[0];
    } elseif (preg_match('/Vivaldi\/([0-9.]+)/i', $userAgent, $m)) {
        return 'Vivaldi ' . explode('.', $m[1])[0];
    } elseif (preg_match('/Brave/i', $userAgent)) {
        return 'Brave';
    } elseif (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $m)) {
        return 'Google Chrome ' . explode('.', $m[1])[0];
    } elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $m)) {
        return 'Mozilla Firefox ' . explode('.', $m[1])[0];
    } elseif (preg_match('/Version\/([0-9.]+).*Safari/i', $userAgent, $m)) {
        return 'Apple Safari ' . explode('.', $m[1])[0];
    } elseif (preg_match('/MSIE\s([0-9.]+)/i', $userAgent, $m)) {
        return 'Internet Explorer ' . $m[1];
    } elseif (preg_match('/Trident\/.*rv:([0-9.]+)/i', $userAgent, $m)) {
        return 'Internet Explorer 11';
    } elseif (preg_match('/curl\/([0-9.]+)/i', $userAgent, $m)) {
        return 'cURL ' . $m[1];
    } elseif (preg_match('/Postman/i', $userAgent)) {
        return 'Postman';
    } elseif (preg_match('/Wget/i', $userAgent)) {
        return 'Wget';
    }

    return 'Inna (' . htmlspecialchars(substr($userAgent, 0, 32)) . ')';
}

function ensureMyCloudTables(mysqli $link): void {
    $createUsers = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `avatar` VARCHAR(255) DEFAULT 'media/default_avatar.png'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($link, $createUsers);

    $createGuests = "CREATE TABLE IF NOT EXISTS `goscieportalu` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    mysqli_query($link, $createGuests);
}

function getUserHomeDir(string $username): string {
    $cleanUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $username);
    if (empty($cleanUser)) {
        $cleanUser = 'guest';
    }
    $baseDir = __DIR__ . '/user-data';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
        @chmod($baseDir, 0777);
    }
    $home = $baseDir . '/' . $cleanUser;
    if (!is_dir($home)) {
        @mkdir($home, 0777, true);
        @chmod($home, 0777);
    }
    return $home;
}

function resolveSafeUserPath(string $username, string $subdir = '', string $filename = ''): ?string {
    $homeDir = getUserHomeDir($username);
    $realHome = realpath($homeDir);
    if ($realHome === false) {
        return null;
    }

    $cleanSubdir = trim($subdir);
    $cleanSubdir = str_replace(['..', '/', '\\'], '', $cleanSubdir);

    $cleanFilename = trim($filename);
    $cleanFilename = basename($cleanFilename);

    $targetPath = $homeDir;
    if (!empty($cleanSubdir)) {
        $targetPath .= '/' . $cleanSubdir;
    }
    if (!empty($cleanFilename)) {
        $targetPath .= '/' . $cleanFilename;
    }

    if (file_exists($targetPath)) {
        $realTarget = realpath($targetPath);
        if ($realTarget === false) {
            return null;
        }
        if ($realTarget === $realHome || str_starts_with($realTarget, $realHome . DIRECTORY_SEPARATOR)) {
            return $realTarget;
        }
        return null;
    }

    $parentDir = dirname($targetPath);
    if (file_exists($parentDir)) {
        $realParent = realpath($parentDir);
        if ($realParent === false) {
            return null;
        }
        if ($realParent === $realHome || str_starts_with($realParent, $realHome . DIRECTORY_SEPARATOR)) {
            return $targetPath;
        }
        return null;
    }

    return null;
}

function formatFileSize(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    if ($i >= count($units)) $i = count($units) - 1;
    $val = round($bytes / pow(1024, $i), 1);
    return $val . ' ' . $units[$i];
}

function getFileCategory(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $categories = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'],
        'audio' => ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a'],
        'video' => ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'],
        'pdf'   => ['pdf'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'],
        'document' => ['doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx'],
        'code'  => ['html', 'htm', 'css', 'js', 'json', 'xml', 'php', 'py', 'c', 'cpp', 'java', 'sql', 'sh']
    ];

    foreach ($categories as $cat => $extensions) {
        if (in_array($ext, $extensions, true)) {
            return $cat;
        }
    }

    return 'other';
}

function removeDirectoryRecursive(string $dir): bool {
    if (!is_dir($dir)) {
        return false;
    }

    $files = scandir($dir);
    if ($files === false) {
        return false;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($fullPath)) {
            removeDirectoryRecursive($fullPath);
        } else {
            @unlink($fullPath);
        }
    }

    return @rmdir($dir);
}

function getUserStorageStats(string $username): array {
    $homeDir = getUserHomeDir($username);
    $totalSize = 0;
    $fileCount = 0;
    $dirCount = 0;

    if (is_dir($homeDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($homeDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $dirCount++;
            } elseif ($item->isFile()) {
                $fileCount++;
                $totalSize += $item->getSize();
            }
        }
    }

    return [
        'file_count' => $fileCount,
        'dir_count' => $dirCount,
        'total_size' => $totalSize,
        'total_size_formatted' => formatFileSize($totalSize)
    ];
}
