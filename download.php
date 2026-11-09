<?php
declare(strict_types=1);
session_start();

require_once 'db.php';

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

$safeFilePath = resolveSafeUserPath($username, $subDir, $fileName);

if ($safeFilePath === null || !is_file($safeFilePath)) {
    http_response_code(404);
    die("Błąd 404: Żądany plik nie istnieje lub brak uprawnień dostępu.");
}

$baseName = basename($safeFilePath);
$fileSize = filesize($safeFilePath);

while (ob_get_level() > 0) {
    ob_end_clean();
}

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
