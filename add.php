<?php
declare(strict_types=1);
session_start();

require_once 'db.php';

$user = trim(htmlentities($_POST['user'] ?? '', ENT_QUOTES, "UTF-8"));
$pass1 = trim(htmlentities($_POST['pass1'] ?? '', ENT_QUOTES, "UTF-8"));
$pass2 = trim(htmlentities($_POST['pass2'] ?? '', ENT_QUOTES, "UTF-8"));

if (empty($user) || empty($pass1) || empty($pass2)) {
    header('Location: rejestruj.php?error=empty_fields');
    exit();
}

// Pkt 6.c.i: Weryfikacja dozwolonych znaków dla loginu (nazwa katalogu na serwerze)
if (!preg_match('/^[a-zA-Z0-9_\-]{3,24}$/', $user)) {
    header('Location: rejestruj.php?error=invalid_username');
    exit();
}

// Zabezpieczenie przed kolizją z plikami i katalogami systemowymi portalu
$reservedNames = [
    'media', 'avatars', 'css', 'js', 'fonts', 'images', 
    'index', 'login', 'logout', 'rejestruj', 'add', 
    'header', 'footer', 'upload', 'select', 'download', 'delete', 
    'mkdir', 'view', 'twoj_css', 'twoj_js', 'db',
    'user-data', 'userdata'
];
if (in_array(strtolower($user), $reservedNames, true)) {
    header('Location: rejestruj.php?error=user_exists');
    exit();
}

if ($pass1 !== $pass2) {
    header('Location: rejestruj.php?error=passwords_mismatch');
    exit();
}

$link = getDbConnection();
ensureMyCloudTables($link);

// Sprawdzenie czy użytkownik już istnieje w bazie danych
$escapedUser = mysqli_real_escape_string($link, $user);
$checkSql = "SELECT id FROM users WHERE username = '$escapedUser'";
$checkResult = mysqli_query($link, $checkSql);
if ($checkResult && mysqli_num_rows($checkResult) > 0) {
    mysqli_close($link);
    header('Location: rejestruj.php?error=user_exists');
    exit();
}

// Obsługa przesyłanego awatara
$avatarPath = 'media/default_avatar.png';

if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['avatar']['tmp_name'];
    $fileSize = $_FILES['avatar']['size'];

    // Limit rozmiaru 2MB
    if ($fileSize > 2 * 1024 * 1024) {
        mysqli_close($link);
        header('Location: rejestruj.php?error=file_too_large');
        exit();
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmpPath);
    finfo_close($finfo);

    if (in_array($mimeType, $allowedMimes, true)) {
        $ext = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'bin'
        };

        $avatarsDir = __DIR__ . '/media/avatars';
        if (!is_dir($avatarsDir)) {
            @mkdir($avatarsDir, 0777, true);
        }

        $newFileName = 'avatar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $avatarsDir . '/' . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $avatarPath = 'media/avatars/' . $newFileName;
        }
    } else {
        mysqli_close($link);
        header('Location: rejestruj.php?error=invalid_file');
        exit();
    }
}

// Zapis nowego użytkownika do bazy
$escapedPass = mysqli_real_escape_string($link, $pass1);
$escapedAvatar = mysqli_real_escape_string($link, $avatarPath);

$insertSql = "INSERT INTO users (username, password, avatar) VALUES ('$escapedUser', '$escapedPass', '$escapedAvatar')";

if (mysqli_query($link, $insertSql)) {
    mysqli_close($link);

    // Pkt 6.c: Utworzenie katalogu macierzystego o nazwie takiej, jak login użytkownika
    $userHomeDir = getUserHomeDir($user);
    if (!is_dir($userHomeDir)) {
        @mkdir($userHomeDir, 0777, true);
    }

    header('Location: login.php?registered=1');
    exit();
} else {
    mysqli_close($link);
    header('Location: rejestruj.php?error=db_error');
    exit();
}
