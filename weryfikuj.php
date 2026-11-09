<?php
declare(strict_types=1);
session_start();

require_once 'db.php';

$user = trim(htmlentities($_POST['user'] ?? '', ENT_QUOTES, "UTF-8"));
$pass = trim(htmlentities($_POST['pass'] ?? '', ENT_QUOTES, "UTF-8"));

if (empty($user) || empty($pass)) {
    header('Location: login.php?error=1');
    exit();
}

$link = getDbConnection();
ensureMyCloudTables($link);

$escapedUser = mysqli_real_escape_string($link, $user);
$sql = "SELECT * FROM users WHERE username = '$escapedUser'";

try {
    $result = mysqli_query($link, $sql);
    $record = mysqli_fetch_assoc($result);
} catch (mysqli_sql_exception $e) {
    $record = false;
}

$clientIp = get_client_ip();
$browser = get_filtered_browser($_SERVER['HTTP_USER_AGENT'] ?? null);

if ($record && $record['password'] === $pass) {
    // Pkt 6.a, 6.b: Poprawne uwierzytelnienie
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = (int)$record['id'];
    $_SESSION['username'] = $record['username'];

    $avatarPath = $record['avatar'] ?? '';
    if (!empty($avatarPath) && file_exists(__DIR__ . '/' . $avatarPath)) {
        $_SESSION['avatar'] = $avatarPath;
    } else {
        $_SESSION['avatar'] = 'media/default_avatar.png';
    }

    // Pkt 6.c: Upewnienie się, że katalog macierzysty użytkownika istnieje i zapisanie ścieżki w sesji
    $userHome = getUserHomeDir($record['username']);
    $_SESSION['user_home'] = $userHome;

    // Pkt 6.b: Rejestracja pomyślnego logowania w tabeli goscieportalu
    $note = "Pomyślne logowanie: " . $record['username'] . " [" . $browser . "]";
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO goscieportalu (ipaddress, datetime, browser) VALUES (?, NOW(), ?)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $clientIp, $note);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_close($link);
    header('Location: index.php');
    exit();
} else {
    // Pkt 6.b: Nieudana próba logowania - rejestracja w goscieportalu
    $failNote = "Błędne logowanie: " . $user . " [" . $browser . "]";
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO goscieportalu (ipaddress, datetime, browser) VALUES (?, NOW(), ?)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $clientIp, $failNote);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_close($link);
    header('Location: login.php?error=1');
    exit();
}
