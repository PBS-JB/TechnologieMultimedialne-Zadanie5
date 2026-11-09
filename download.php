<?php
declare(strict_types=1);
session_start();

require_once 'db.php';

// Weryfikacja sesji użytkownika
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    die("Brak uprawnień. Zaloguj się do portalu.");
}

$username = $_SESSION['username'] ?? '';
$fileName = trim($_GET['file'] ?? '');
$subDir = trim($_GET['dir'] ?? '');

if (empty($fileName)) {
    http_response_code(400);
    die("Brak nazwy pliku do pobrania.");
}

// Bezpieczne rozwiązanie ścieżki i zapobieganie Path Traversal (Pkt 6.k)
$safeFilePath = resolveSafeUserPath($username, $subDir, $fileName);

if ($safeFilePath === null || !is_file($safeFilePath)) {
    http_response_code(404);
    die("Błąd 404: Żądany plik nie istnieje lub brak uprawnień dostępu.");
}

$baseName = basename($safeFilePath);
$fileSize = filesize($safeFilePath);

// Oczyszczenie buforów wyjściowych PHP, aby zapobiec uszkodzeniu plików binarnych (np. PDF, MP3, DOCX)
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Nagłówki wymuszające poprawne pobranie pliku z serwera na dysk lokalny (Pkt 6.k, 7.c.ii)
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($baseName) . '"; filename*=UTF-8\'\'' . rawurlencode($baseName));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

readfile($safeFilePath);
exit();
