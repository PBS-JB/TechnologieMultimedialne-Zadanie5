<?php
declare(strict_types=1);  /* Ta linia musi być pierwsza */
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$username = $_SESSION['username'] ?? 'user';
$homeDir = getUserHomeDir($username);

// Pobranie bieżącego katalogu (opcjonalny parametr dir)
$currentSubdir = trim($_GET['dir'] ?? '');
$currentSubdir = str_replace(['..', '/', '\\'], '', $currentSubdir);

// Lista dostępnych podkatalogów użytkownika do wyboru w formularzu
$availableSubdirs = [];
if (is_dir($homeDir)) {
    $items = scandir($homeDir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir($homeDir . '/' . $item)) {
                $availableSubdirs[] = $item;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">  
	<title>Prześlij plik (Upload) | MyCloud</title>
	
	<!-- Bootstrap 5 CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">
	<link rel="stylesheet" type="text/css" href="twoj_css.css">
	
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
	<script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
	<script type="text/javascript" src="twoj_js.js"></script> 
</head>

<body onload="myLoadHeader()">
	<div id='myHeader'></div>	

	<main class="py-4"> 
		<div class="container">
			
			<div class="row justify-content-center">
				<div class="col-lg-8 col-xl-7">
					
					<div class="card bg-coffee-dark border-coffee shadow-lg">
						<div class="card-header bg-coffee-surface border-coffee py-3 d-flex align-items-center justify-content-between">
							<div class="d-flex align-items-center">
								<img src="media/menu_icons/upload.svg" height="22" class="me-2 filter-accent" alt="">
								<h1 class="h5 mb-0 text-light fw-bold">Przesyłanie pliku na dysk</h1>
							</div>
							<a href="index.php<?= !empty($currentSubdir) ? '?dir=' . urlencode($currentSubdir) : '' ?>" class="btn btn-outline-light btn-sm">
								Powrót do dysku
							</a>
						</div>
						
						<div class="card-body p-4">
							<p class="text-muted small mb-4">
								Wybierz plik z lokalnego dysku komputera, aby przesłać go na serwer do Twojej chmury.
							</p>

							<!-- Formularz wyboru pliku zgodny z instrukcją (Pkt 6.i) -->
							<form action="upload.php" method="post" enctype="multipart/form-data">
								
								<?php if (!empty($availableSubdirs)): ?>
									<div class="mb-3">
										<label for="target_subdir" class="form-label text-light fw-semibold">Katalog docelowy</label>
										<select name="target_subdir" id="target_subdir" class="form-select bg-coffee-surface text-light border-coffee">
											<option value="" <?= empty($currentSubdir) ? 'selected' : '' ?>>Katalog główny</option>
											<?php foreach ($availableSubdirs as $sdir): ?>
												<option value="<?= htmlspecialchars($sdir) ?>" <?= $currentSubdir === $sdir ? 'selected' : '' ?>>
													<?= htmlspecialchars($sdir) ?>
												</option>
											<?php endforeach; ?>
										</select>
										<div class="form-text text-muted small">Wybierz, czy plik ma trafić do katalogu głównego, czy do podkatalogu.</div>
									</div>
								<?php else: ?>
									<input type="hidden" name="target_subdir" value="">
								<?php endif; ?>

								<div class="mb-4">
									<label for="fileToUpload" class="form-label text-light fw-semibold">Wybierz plik z dysku komputera</label>
									<div class="upload-dropzone p-4 text-center border-dashed rounded-3">
										<img src="media/menu_icons/upload.svg" width="40" height="40" class="mb-2 filter-accent" alt="">
										<p class="text-light mb-2 fw-semibold">Wskaż plik do przesłania</p>
										<input type="file" name="fileToUpload" id="fileToUpload" class="form-control bg-coffee-surface text-light border-coffee mb-2" required onchange="showSelectedFileInfo(this)">
										<div id="fileInfoDisplay" class="small text-muted mt-2">Dowolny format (mp3, mp4, jpg, png, pdf, docx, zip...)</div>
									</div>
								</div>

								<div class="d-grid gap-2">
									<input type="submit" value="Prześlij plik na serwer (Upload)" name="submit" class="btn btn-coffee py-2 fw-bold">
								</div>
							</form>
						</div>
					</div>

				</div>
			</div>

		</div>
	</main>

	<?php include 'footer.php'; ?>

	<script type="text/javascript">
		function showSelectedFileInfo(input) {
			if (input.files && input.files[0]) {
				const file = input.files[0];
				const sizeKB = (file.size / 1024).toFixed(1);
				$('#fileInfoDisplay').html('Wybrany plik: <strong class="text-accent">' + file.name + '</strong> (' + sizeKB + ' KB)');
			}
		}
	</script>
</body>
</html>
