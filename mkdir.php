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
$userHomeDir = getUserHomeDir($username);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$folderName = trim($_POST['folder_name'] ?? '');
$currentDir = trim($_POST['current_dir'] ?? '');

// Pkt 6.f.ii: Wewnątrz podkatalogów nie tworzymy dalszych podkatalogów
if (!empty($currentDir)) {
    header('Location: index.php?dir=' . urlencode($currentDir) . '&error=nested_folders_not_allowed');
    exit();
}

// Walidacja nazwy folderu (tylko bezpieczne znaki, brak znaków specjalnych i ścieżek)
$cleanFolderName = preg_replace('/[^\p{L}\p{N}_\-\s]/u', '', $folderName);
$cleanFolderName = trim($cleanFolderName);

if (empty($cleanFolderName)) {
    header('Location: index.php?error=invalid_folder_name');
    exit();
}

$targetPath = $userHomeDir . '/' . $cleanFolderName;

if (file_exists($targetPath)) {
    header('Location: index.php?error=folder_already_exists');
    exit();
}

// Pkt 6.f.i: Utworzenie nowego podkatalogu za pomocą mkdir()
if (@mkdir($targetPath, 0777, true)) {
    header('Location: index.php?msg=folder_created&folder=' . urlencode($cleanFolderName));
    exit();
} else {
    header('Location: index.php?error=folder_create_failed');
    exit();
}
