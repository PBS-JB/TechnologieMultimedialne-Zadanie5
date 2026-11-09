<?php
declare(strict_types=1);
session_start();

require_once 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    die("Błąd: Nieokreślony użytkownik sesji.");
}

$userHomeDir = getUserHomeDir($username);

$targetSubdir = trim($_POST['target_subdir'] ?? ($_GET['dir'] ?? ''));
$targetSubdir = str_replace(['..', '/', '\\'], '', $targetSubdir);

$targetDir = $userHomeDir;
if (!empty($targetSubdir)) {
    $safeSubdirPath = resolveSafeUserPath($username, $targetSubdir);
    if ($safeSubdirPath !== null && is_dir($safeSubdirPath)) {
        $targetDir = $safeSubdirPath;
    }
}

if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0777, true);
    @chmod($targetDir, 0777);
}

$statusMessage = '';
$isSuccess = false;
$uploadedFileName = '';

if (isset($_FILES['fileToUpload'])) {
    $uploadError = $_FILES['fileToUpload']['error'];

    if ($uploadError === UPLOAD_ERR_OK) {
        $originalName = basename($_FILES['fileToUpload']['name']);
        
        $cleanName = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $originalName);
        if (empty($cleanName)) {
            $cleanName = 'plik_' . time();
        }

        $targetFile = $targetDir . '/' . $cleanName;

        if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
            @chmod($targetFile, 0666);
            $isSuccess = true;
            $uploadedFileName = $cleanName;
            $statusMessage = "Plik " . htmlspecialchars($cleanName) . " został pomyślnie przesłany na dysk.";
        } else {
            if (!is_writable($targetDir)) {
                $statusMessage = "Błąd: Brak uprawnień do zapisu w katalogu docelowym (" . htmlspecialchars(basename($targetDir)) . ").";
            } elseif (!file_exists($_FILES['fileToUpload']['tmp_name'])) {
                $statusMessage = "Błąd: Plik tymczasowy nie istnieje lub został usunięty przez serwer.";
            } else {
                $statusMessage = "Błąd podczas zapisywania pliku na dysku serwera.";
            }
        }
    } else {
        $statusMessage = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE   => "Błąd: Rozmiar pliku przekracza limit dozwolony przez serwer (upload_max_filesize).",
            UPLOAD_ERR_FORM_SIZE  => "Błąd: Rozmiar pliku przekracza limit formularza.",
            UPLOAD_ERR_PARTIAL    => "Błąd: Plik został przesłany tylko częściowo.",
            UPLOAD_ERR_NO_FILE    => "Błąd: Nie wybrano żadnego pliku do przesłania.",
            UPLOAD_ERR_NO_TMP_DIR => "Błąd: Brak katalogu tymczasowego na serwerze.",
            UPLOAD_ERR_CANT_WRITE => "Błąd: Nie udało się zapisać pliku na dysku serwera.",
            default               => "Błąd przesyłania pliku (kod błędu: $uploadError)."
        };
    }
} else {
    $statusMessage = "Błąd: Nie odebrano żadnego pliku z formularza.";
}

$isAjaxOrCli = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['raw']) || isset($_GET['raw'])
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if ($isAjaxOrCli) {
    echo $statusMessage;
    exit();
}

$returnTo = $_POST['return_to'] ?? '';
if ($isSuccess && $returnTo === 'index') {
    $queryParams = ['msg' => 'uploaded', 'file' => $uploadedFileName];
    if (!empty($targetSubdir)) {
        $queryParams['dir'] = $targetSubdir;
    }
    header('Location: index.php?' . http_build_query($queryParams));
    exit();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Status przesyłania pliku | MyCloud</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="twoj_css.css">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
	<script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
	<script type="text/javascript" src="twoj_js.js"></script>
</head>
<body onload="myLoadHeader()">
	<div id='myHeader'></div>
	<main class="py-5">
		<div class="container text-center">
			<div class="card bg-coffee-dark border-coffee shadow-lg mx-auto p-4" style="max-width: 620px;">
				<div class="mb-3">
					<img src="media/menu_icons/cloud.svg" width="48" height="48" class="mb-2 <?= $isSuccess ? 'filter-accent' : 'filter-muted' ?>" alt="">
					<h2 class="h5 text-light fw-bold">Status przesyłania pliku</h2>
				</div>
				
				<div class="alert <?= $isSuccess ? 'alert-success' : 'alert-danger' ?> py-3 mb-4">
					<?= $statusMessage ?>
				</div>

				<div class="d-flex justify-content-center gap-2">
					<a href="select.php<?= !empty($targetSubdir) ? '?dir=' . urlencode($targetSubdir) : '' ?>" class="btn btn-outline-light">
						Wyślij inny plik
					</a>
					<a href="index.php<?= !empty($targetSubdir) ? '?dir=' . urlencode($targetSubdir) : '' ?>" class="btn btn-coffee fw-semibold">
						Przejdź do dysku
					</a>
				</div>
			</div>
		</div>
	</main>
	<?php include 'footer.php'; ?>
</body>
</html>
