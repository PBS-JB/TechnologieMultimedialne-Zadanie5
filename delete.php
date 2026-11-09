<?php
declare(strict_types=1);
session_start();

require_once 'db.php';

// Weryfikacja sesji użytkownika
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? '';
$item = trim($_REQUEST['item'] ?? '');
$subDir = trim($_REQUEST['dir'] ?? '');

if (empty($item)) {
    header('Location: index.php?error=no_item_specified');
    exit();
}

// Bezpieczne rozwiązanie ścieżki i blokada Path Traversal
$safePath = resolveSafeUserPath($username, $subDir, $item);

$returnUrl = 'index.php';
$params = [];
if (!empty($subDir)) {
    $params['dir'] = $subDir;
}

if ($safePath === null || !file_exists($safePath)) {
    $params['error'] = 'item_not_found';
    header('Location: ' . $returnUrl . '?' . http_build_query($params));
    exit();
}

// Upewniamy się, że użytkownik nie próbuje usunąć własnego katalogu domowego
$homeDir = realpath(getUserHomeDir($username));
if ($safePath === $homeDir) {
    $params['error'] = 'cannot_delete_home';
    header('Location: ' . $returnUrl . '?' . http_build_query($params));
    exit();
}

if (is_file($safePath)) {
    // Pkt 6.g.ii: Usuwanie pliku za pomocą unlink()
    if (@unlink($safePath)) {
        $params['msg'] = 'file_deleted';
        $params['item'] = basename($safePath);
    } else {
        $params['error'] = 'file_delete_failed';
    }
} elseif (is_dir($safePath)) {
    // Pkt 6.g.iii: Usuwanie podkatalogu (z uprzednim usunięciem wszystkich plików wewnątrz)
    if (removeDirectoryRecursive($safePath)) {
        $params['msg'] = 'folder_deleted';
        $params['item'] = basename($safePath);
    } else {
        $params['error'] = 'folder_delete_failed';
    }
}

header('Location: ' . $returnUrl . '?' . http_build_query($params));
exit();
