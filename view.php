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
    die("Brak nazwy pliku.");
}

$safeFilePath = resolveSafeUserPath($username, $subDir, $fileName);

if ($safeFilePath === null || !is_file($safeFilePath)) {
    http_response_code(404);
    die("Błąd 404: Plik nie istnieje.");
}

$fileSize = filesize($safeFilePath);
$ext = strtolower(pathinfo($safeFilePath, PATHINFO_EXTENSION));

$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'ogg'  => 'audio/ogg',
    'm4a'  => 'audio/mp4',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogv'  => 'video/ogg',
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain; charset=utf-8',
    'html' => 'text/html; charset=utf-8',
    'json' => 'application/json'
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

while (ob_get_level() > 0) {
    ob_end_clean();
}

$fp = fopen($safeFilePath, 'rb');
if (!$fp) {
    http_response_code(500);
    die("Nie można otworzyć pliku do odczytu.");
}

$start = 0;
$end = $fileSize - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = (int)$matches[1];
        if (!empty($matches[2])) {
            $end = (int)$matches[2];
        }
    }
    http_response_code(206);
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
} else {
    http_response_code(200);
}

$length = $end - $start + 1;
header("Content-Type: {$mimeType}");
header("Content-Length: {$length}");
header("Accept-Ranges: bytes");
header("Content-Disposition: inline; filename=\"" . rawurlencode(basename($safeFilePath)) . "\"");
header("Cache-Control: public, max-age=3600");

fseek($fp, $start);
$buffer = 1024 * 64;
while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
    if ($pos + $buffer > $end) {
        $buffer = $end - $pos + 1;
    }
    echo fread($fp, $buffer);
    flush();
}
fclose($fp);
exit();
