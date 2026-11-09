<?php
declare(strict_types=1);  /* Ta linia musi być pierwsza */
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$link = getDbConnection();
ensureMyCloudTables($link);
mysqli_close($link);

$username = $_SESSION['username'] ?? 'user';
$userHomeDir = getUserHomeDir($username);
$_SESSION['user_home'] = $userHomeDir;

// Pkt 6.e, 6.h: Obsługa nawigacji po podkatalogach (parametr dir)
$currentSubdir = trim($_GET['dir'] ?? '');
$currentSubdir = str_replace(['..', '/', '\\'], '', $currentSubdir);

$isInsideSubdir = !empty($currentSubdir);
$currentDir = $userHomeDir;

if ($isInsideSubdir) {
    $resolvedDir = resolveSafeUserPath($username, $currentSubdir);
    if ($resolvedDir !== null && is_dir($resolvedDir)) {
        $currentDir = $resolvedDir;
    } else {
        // Próba wejścia do nieistniejącego lub niedozwolonego katalogu - powrót do katalogu głównego
        header('Location: index.php?error=invalid_dir');
        exit();
    }
}

// Odczyt zawartości bieżącego katalogu (Pkt 6.d, 6.e)
$subdirectories = [];
$files = [];

if (is_dir($currentDir)) {
    $scan = scandir($currentDir);
    if ($scan !== false) {
        foreach ($scan as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $currentDir . '/' . $entry;

            if (is_dir($fullPath)) {
                // Podkatalog
                $itemCount = 0;
                $dirScan = @scandir($fullPath);
                if ($dirScan !== false) {
                    $itemCount = count(array_diff($dirScan, ['.', '..']));
                }
                $subdirectories[] = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'item_count' => $itemCount,
                    'modified' => date('Y-m-d H:i', filemtime($fullPath))
                ];
            } elseif (is_file($fullPath)) {
                // Plik
                $size = filesize($fullPath);
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                $category = getFileCategory($entry);

                $files[] = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'size' => $size,
                    'size_formatted' => formatFileSize($size),
                    'modified' => date('Y-m-d H:i', filemtime($fullPath)),
                    'ext' => $ext,
                    'category' => $category
                ];
            }
        }
    }
}

// Pobranie statystyk całej chmury użytkownika
$stats = getUserStorageStats($username);

// Preferowany tryb widoku (Grid kafelki / Tabela lista)
$currentView = $_GET['view'] ?? ($_SESSION['cloud_view'] ?? 'grid');
if (in_array($currentView, ['grid', 'table'], true)) {
    $_SESSION['cloud_view'] = $currentView;
} else {
    $currentView = 'grid';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">  
	<meta name="description" content="MyCloud - Dysk sieciowy w chmurze">
	<meta name="author" content="Jakub Baka">
	<title>Mój Dysk (MyCloud) | Jakub Baka</title>
	
	<!-- Bootstrap 5 CSS & DataTables -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">
	<link rel="stylesheet" type="text/css" href="twoj_css.css">
	
	<!-- Skrypty JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
	<script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
	<script type="text/javascript" src="twoj_js.js"></script> 

	<script type="text/javascript">
		$(document).ready(function() {
			if ($('#cloudFilesTable').length) {
				$('#cloudFilesTable').DataTable({
					"language": {
						"url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Polish.json"
					},
					"order": [[ 0, "asc" ]],
					"pageLength": 10,
					"responsive": true
				});
			}
		});
	</script>
</head>

<body onload="myLoadHeader()">
	<div id='myHeader'></div>	

	<main class="py-4"> 
		<div class="container-fluid px-lg-4">
			
			<!-- Pasek informacyjny i komunikaty zwrotne -->
			<?php if (isset($_GET['msg'])): ?>
				<?php $m = $_GET['msg']; ?>
				<?php if ($m === 'uploaded'): ?>
					<div class="alert alert-success alert-dismissible fade show" role="alert">
						<strong>Sukces!</strong> Plik <strong><?= htmlspecialchars($_GET['file'] ?? '') ?></strong> został pomyślnie przesłany na dysk.
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php elseif ($m === 'folder_created'): ?>
					<div class="alert alert-success alert-dismissible fade show" role="alert">
						<strong>Sukces!</strong> Podkatalog <strong><?= htmlspecialchars($_GET['folder'] ?? '') ?></strong> został utworzony.
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php elseif ($m === 'file_deleted'): ?>
					<div class="alert alert-info alert-dismissible fade show" role="alert">
						Plik <strong><?= htmlspecialchars($_GET['item'] ?? '') ?></strong> został pomyślnie usunięty.
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php elseif ($m === 'folder_deleted'): ?>
					<div class="alert alert-info alert-dismissible fade show" role="alert">
						Podkatalog <strong><?= htmlspecialchars($_GET['item'] ?? '') ?></strong> wraz z całą zawartością został pomyślnie usunięty.
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if (isset($_GET['error'])): ?>
				<div class="alert alert-danger alert-dismissible fade show" role="alert">
					<strong>Uwaga:</strong> 
					<?php
						$err = $_GET['error'];
						if ($err === 'nested_folders_not_allowed') echo 'Zgodnie z wytycznymi tworzenie podkatalogów wewnątrz podkatalogów jest zablokowane.';
						elseif ($err === 'invalid_folder_name') echo 'Podana nazwa katalogu jest nieprawidłowa.';
						elseif ($err === 'folder_already_exists') echo 'Katalog o podanej nazwie już istnieje.';
						elseif ($err === 'invalid_dir') echo 'Wybrany katalog nie istnieje lub brak uprawnień.';
						else echo 'Wystąpił błąd podczas wykonywania operacji na plikach.';
					?>
					<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				</div>
			<?php endif; ?>

			<!-- Nagłówek dysku i statystyki -->
			<div class="row align-items-center mb-4 g-3">
				<div class="col-12 col-md-auto me-auto">
					<div class="d-flex align-items-center">
						<div class="cloud-header-icon me-3">
							<img src="media/menu_icons/cloud.svg" width="36" height="36" class="filter-accent" alt="">
						</div>
						<div>
							<h1 class="h3 fw-bold mb-0 text-light">Mój Dysk Sieciowy</h1>
							<!-- Ścieżka nawigacyjna (Breadcrumb) -->
							<nav aria-label="breadcrumb">
								<ol class="breadcrumb mb-0 small">
									<li class="breadcrumb-item">
										<a href="index.php" class="text-accent text-decoration-none">
											<?= htmlspecialchars($username) ?>
										</a>
									</li>
									<?php if ($isInsideSubdir): ?>
										<li class="breadcrumb-item active text-light fw-bold" aria-current="page">
											<?= htmlspecialchars($currentSubdir) ?>
										</li>
									<?php endif; ?>
								</ol>
							</nav>
						</div>
					</div>
				</div>

				<!-- Liczniki przestrzeni dyskowej -->
				<div class="col-12 col-md-auto">
					<div class="d-flex flex-wrap gap-2 align-items-center">
						<span class="badge bg-coffee-surface border-coffee text-light p-2 small">
							Plików: <strong class="text-accent"><?= $stats['file_count'] ?></strong>
						</span>
						<span class="badge bg-coffee-surface border-coffee text-light p-2 small">
							Folderów: <strong class="text-accent"><?= $stats['dir_count'] ?></strong>
						</span>
						<span class="badge bg-coffee-surface border-coffee text-light p-2 small">
							Zajęte: <strong class="text-accent"><?= $stats['total_size_formatted'] ?></strong>
						</span>
					</div>
				</div>

				<!-- Przyciski akcji: Upload, Nowy folder, Przełącznik widoku -->
				<div class="col-12 col-md-auto d-flex flex-wrap gap-2">
					
					<!-- Pkt 6.h.ii, 6.h.iii: Przycisk Poziom wyżej (wyświetlany tylko w podkatalogu) -->
					<?php if ($isInsideSubdir): ?>
						<a href="index.php" class="btn btn-outline-warning btn-sm d-flex align-items-center" title="Wróć do katalogu macierzystego">
							<img src="media/menu_icons/arrow-up.svg" height="16" class="me-1 filter-white" alt="">
							Poziom wyżej
						</a>
					<?php endif; ?>

					<!-- Pkt 6.f.i, 6.f.ii: Ikona i przycisk Nowy podkatalog (wyświetlany tylko w katalogu macierzystym) -->
					<?php if (!$isInsideSubdir): ?>
						<button type="button" class="btn btn-outline-coffee btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#newFolderModal">
							<img src="media/menu_icons/folder-plus.svg" height="16" class="me-1 filter-accent" alt="">
							Nowy katalog
						</button>
					<?php endif; ?>

					<!-- Pkt 6.i, 6.j: Przycisk przesyłania plików -->
					<button type="button" class="btn btn-coffee btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#uploadModal">
						<img src="media/menu_icons/upload.svg" height="16" class="me-1 filter-white" alt="">
						Wyślij plik
					</button>

					<!-- Pkt 8.a: Przełącznik widoku (Kafelki / Tabela) -->
					<div class="btn-group btn-group-sm">
						<a href="?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])) ?>" class="btn <?= $currentView === 'grid' ? 'btn-accent text-dark fw-bold' : 'btn-outline-secondary text-light' ?>" title="Widok kafelków z miniaturami i playerami">
							Kafelki
						</a>
						<a href="?<?= http_build_query(array_merge($_GET, ['view' => 'table'])) ?>" class="btn <?= $currentView === 'table' ? 'btn-accent text-dark fw-bold' : 'btn-outline-secondary text-light' ?>" title="Zwykła lista tabelaryczna">
							Lista
						</a>
					</div>

				</div>
			</div>

			<!-- SEKCJA PODKATALOGÓW (Pkt 6.d, 6.e, 6.h) -->
			<?php if (!empty($subdirectories)): ?>
				<div class="mb-4">
					<h2 class="h6 text-muted text-uppercase fw-bold letter-spacing-1 mb-3">Katalogi</h2>
					<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
						<?php foreach ($subdirectories as $dir): ?>
							<div class="col">
								<div class="card bg-coffee-dark border-coffee card-hover h-100 shadow-sm">
									<div class="card-body p-3 d-flex align-items-center justify-content-between">
										<!-- Kliknięcie nazwy podkatalogu przenosi do niego (Pkt 6.e.ii, 6.h.i) -->
										<a href="index.php?dir=<?= urlencode($dir['name']) ?>" class="d-flex align-items-center text-decoration-none text-light text-truncate me-2">
											<div class="folder-icon-box me-3">
												<img src="media/menu_icons/folder.svg" width="28" height="28" class="filter-accent" alt="">
											</div>
											<div class="text-truncate">
												<div class="fw-semibold text-truncate"><?= htmlspecialchars($dir['name']) ?></div>
												<small class="text-muted"><?= $dir['item_count'] ?> elementów</small>
											</div>
										</a>
										<!-- Ikona usuwania podkatalogu (Pkt 6.g) -->
										<a href="delete.php?item=<?= urlencode($dir['name']) ?>&dir=<?= urlencode($currentSubdir) ?>" 
										   class="btn btn-sm btn-outline-danger p-1 border-0" 
										   onclick="return confirm('Czy na pewno chcesz usunąć katalog \'<?= htmlspecialchars(addslashes($dir['name'])) ?>\' i wszystkie znajdujące się w nim pliki?');" 
										   title="Usuń podkatalog">
											<img src="media/menu_icons/trash.svg" width="16" height="16" class="filter-white" alt="">
										</a>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- SEKCJA PLIKÓW (Pkt 6.d, 6.e, 6.k, 8.a) -->
			<div class="mb-4">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h2 class="h6 text-muted text-uppercase fw-bold letter-spacing-1 mb-0">
						Pliki <?= $isInsideSubdir ? 'w katalogu: /' . htmlspecialchars($currentSubdir) : 'w katalogu głównym' ?>
					</h2>
					<span class="badge bg-secondary"><?= count($files) ?> plików</span>
				</div>

				<?php if (empty($files) && empty($subdirectories)): ?>
					<!-- Pkt 6.d.ii: Informacja o pustym katalogu -->
					<div class="card bg-coffee-dark border-coffee text-center py-5 shadow-sm">
						<div class="card-body">
							<img src="media/menu_icons/cloud.svg" width="64" height="64" class="filter-muted mb-3 opacity-50" alt="">
							<h3 class="h5 text-light fw-bold">Ten katalog jest pusty</h3>
							<p class="text-muted small mb-4">Nie przesłano jeszcze żadnych plików do tego folderu.</p>
							<button type="button" class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#uploadModal">
								<img src="media/menu_icons/upload.svg" height="16" class="me-1 filter-white" alt="">
								Prześlij pierwszy plik
							</button>
						</div>
					</div>
				<?php elseif ($currentView === 'grid'): ?>

					<!-- =================== TRYB 1: KAFELKI Z MULTIMEDIAMI (Pkt 6.k, 8.a.ii) =================== -->
					<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3">
						<?php foreach ($files as $file): 
							$viewUrl = 'view.php?file=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
							$downloadUrl = 'download.php?file=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
							$deleteUrl = 'delete.php?item=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
						?>
							<div class="col">
								<div class="card bg-coffee-dark border-coffee card-hover h-100 shadow-sm file-card">
									
									<!-- Strefa podglądu multimediów w zależności od kategorii -->
									<div class="file-card-preview bg-black d-flex align-items-center justify-content-center position-relative overflow-hidden">
										
										<?php if ($file['category'] === 'image'): ?>
											<!-- Plik graficzny: miniatura, po kliknięciu pełny rozmiar w Lightbox (Pkt 6.k.iv, 8.a.ii) -->
											<img src="<?= $viewUrl ?>" alt="<?= htmlspecialchars($file['name']) ?>" class="img-fluid file-img-thumb" onclick="openLightbox('<?= $viewUrl ?>', '<?= htmlspecialchars(addslashes($file['name'])) ?>')" title="Kliknij, aby otworzyć obraz w pełnym rozmiarze">
											<div class="preview-overlay">
												<span class="badge bg-dark bg-opacity-75 text-light small">Powiększ</span>
											</div>

										<?php elseif ($file['category'] === 'video'): ?>
											<!-- Odtwarzacz MP4 / Wideo HTML5 bez autoplay (Pkt 6.k.iv) -->
											<video controls preload="metadata" class="w-100 file-video-player">
												<source src="<?= $viewUrl ?>" type="video/<?= $file['ext'] === 'mp4' ? 'mp4' : 'webm' ?>">
												Twoja przeglądarka nie wspiera tagu wideo.
											</video>

										<?php elseif ($file['category'] === 'audio'): ?>
											<!-- Odtwarzacz MP3 / Audio HTML5 bez autoplay (Pkt 6.k.iv) -->
											<div class="w-100 p-2 text-center">
												<div class="audio-icon-box mb-2">
													<img src="media/menu_icons/star.svg" width="32" height="32" class="filter-accent" alt="">
												</div>
												<audio controls preload="metadata" class="w-100 file-audio-player">
													<source src="<?= $viewUrl ?>" type="audio/<?= $file['ext'] === 'mp3' ? 'mpeg' : 'wav' ?>">
													Twoja przeglądarka nie wspiera odtwarzacza audio.
												</audio>
											</div>

										<?php elseif ($file['category'] === 'pdf'): ?>
											<!-- Dokument PDF: ikona i szybki podgląd w nowej karcie -->
											<div class="text-center p-3">
												<div class="doc-icon-badge bg-danger text-light mb-2">PDF</div>
												<a href="<?= $viewUrl ?>" target="_blank" class="btn btn-sm btn-outline-light">
													Otwórz dokument
												</a>
											</div>

										<?php else: ?>
											<!-- Pozostałe pliki: ikona formatu (Pkt 6.k.iv, 8.a.iii) -->
											<div class="text-center p-3">
												<span class="badge bg-secondary fs-6 text-uppercase mb-2"><?= htmlspecialchars($file['ext']) ?></span>
												<div class="text-muted small">Plik binarny</div>
											</div>
										<?php endif; ?>

									</div>

									<!-- Informacje o pliku i stopka karty -->
									<div class="card-body p-3 d-flex flex-column justify-content-between">
										<div class="mb-2">
											<div class="d-flex align-items-start justify-content-between">
												<a href="<?= $downloadUrl ?>" class="fw-semibold text-light text-decoration-none text-truncate me-2 flex-grow-1" title="<?= htmlspecialchars($file['name']) ?>">
													<?= htmlspecialchars($file['name']) ?>
												</a>
											</div>
											<div class="d-flex justify-content-between text-muted small mt-1">
												<span><?= $file['size_formatted'] ?></span>
												<span><?= $file['modified'] ?></span>
											</div>
										</div>

										<!-- Przyciski akcji: Pobierz (Download) oraz Usuń (Pkt 6.g, 6.k) -->
										<div class="d-flex justify-content-between align-items-center pt-2 border-top border-coffee">
											<a href="<?= $downloadUrl ?>" class="btn btn-sm btn-outline-info d-flex align-items-center" title="Ściągnij na dysk lokalny">
												<img src="media/menu_icons/download.svg" height="14" class="me-1 filter-white" alt="">
												Pobierz
											</a>
											
											<div class="d-flex gap-1">
												<a href="<?= $viewUrl ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Podgląd w oknie">
													Otwórz
												</a>
												<a href="<?= $deleteUrl ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Czy na pewno chcesz usunąć plik: <?= htmlspecialchars(addslashes($file['name'])) ?>?');" title="Usuń plik">
													<img src="media/menu_icons/trash.svg" height="14" class="filter-white" alt="">
												</a>
											</div>
										</div>
									</div>

								</div>
							</div>
						<?php endforeach; ?>
					</div>

				<?php else: ?>

					<!-- =================== TRYB 2: TABELA SZCZEGÓŁOWA (Pkt 8.a.i, 8.a.iv) =================== -->
					<div class="card bg-coffee-dark border-coffee shadow-sm">
						<div class="card-body p-3">
							<div class="table-responsive">
								<table id="cloudFilesTable" class="table table-dark table-hover table-striped align-middle mb-0">
									<thead>
										<tr>
											<th>Nazwa pliku</th>
											<th>Kategoria</th>
											<th>Rozmiar</th>
											<th>Data modyfikacji</th>
											<th class="text-center">Akcje</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($files as $file): 
											$viewUrl = 'view.php?file=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
											$downloadUrl = 'download.php?file=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
											$deleteUrl = 'delete.php?item=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
										?>
											<tr>
												<td>
													<a href="<?= $downloadUrl ?>" class="fw-semibold text-light text-decoration-none">
														<?= htmlspecialchars($file['name']) ?>
													</a>
												</td>
												<td>
													<span class="badge bg-secondary text-uppercase small"><?= htmlspecialchars($file['category']) ?> (<?= htmlspecialchars($file['ext']) ?>)</span>
												</td>
												<td class="font-monospace small"><?= $file['size_formatted'] ?></td>
												<td class="small text-nowrap"><?= $file['modified'] ?></td>
												<td class="text-center text-nowrap">
													<a href="<?= $downloadUrl ?>" class="btn btn-sm btn-outline-info me-1" title="Pobierz">
														<img src="media/menu_icons/download.svg" height="14" class="filter-white" alt=""> Pobierz
													</a>
													<a href="<?= $viewUrl ?>" target="_blank" class="btn btn-sm btn-outline-light me-1" title="Podgląd">
														Podgląd
													</a>
													<a href="<?= $deleteUrl ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Czy na pewno chcesz usunąć plik: <?= htmlspecialchars(addslashes($file['name'])) ?>?');" title="Usuń">
														<img src="media/menu_icons/trash.svg" height="14" class="filter-white" alt="">
													</a>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

				<?php endif; ?>
			</div>

		</div>
	</main>

	<!-- MODAL: Nowy podkatalog (Pkt 6.f.i) -->
	<?php if (!$isInsideSubdir): ?>
		<div class="modal fade" id="newFolderModal" tabindex="-1" aria-labelledby="newFolderModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content bg-coffee-dark border-coffee text-light">
					<form action="mkdir.php" method="post">
						<div class="modal-header bg-coffee-surface border-coffee">
							<h5 class="modal-title h6 fw-bold" id="newFolderModalLabel">
								<img src="media/menu_icons/folder-plus.svg" height="18" class="me-2 filter-accent" alt="">
								Utwórz nowy podkatalog
							</h5>
							<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
						</div>
						<div class="modal-body p-4">
							<div class="mb-3">
								<label for="folder_name" class="form-label">Nazwa podkatalogu</label>
								<input type="text" class="form-control bg-coffee-surface text-light border-coffee" id="folder_name" name="folder_name" required pattern="[\p{L}\p{N}_\-\s]{1,40}" placeholder="np. Dokumenty, Zdjęcia, Projekty">
								<div class="form-text text-muted small">Dozwolone litery, cyfry, spacje, myślniki i znaki podkreślenia.</div>
							</div>
							<input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentSubdir) ?>">
						</div>
						<div class="modal-footer border-coffee">
							<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
							<button type="submit" class="btn btn-coffee">Utwórz katalog</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- MODAL: Przesyłanie pliku (Pkt 6.i, 6.j) -->
	<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content bg-coffee-dark border-coffee text-light">
				<form action="upload.php" method="post" enctype="multipart/form-data">
					<div class="modal-header bg-coffee-surface border-coffee">
						<h5 class="modal-title h6 fw-bold" id="uploadModalLabel">
							<img src="media/menu_icons/upload.svg" height="18" class="me-2 filter-accent" alt="">
							Prześlij plik na serwer
						</h5>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
					</div>
					<div class="modal-body p-4">
						<input type="hidden" name="return_to" value="index">
						<input type="hidden" name="target_subdir" value="<?= htmlspecialchars($currentSubdir) ?>">

						<div class="mb-3">
							<label for="modalFileToUpload" class="form-label">Wybierz plik z dysku komputera</label>
							<input type="file" class="form-control bg-coffee-surface text-light border-coffee" id="modalFileToUpload" name="fileToUpload" required>
							<div class="form-text text-muted small">Wsparcie dla każdego formatu (obraz, wideo, dźwięk, PDF, dokumenty).</div>
						</div>
					</div>
					<div class="modal-footer border-coffee">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
						<button type="submit" name="submit" class="btn btn-coffee">Rozpocznij wysyłanie</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- MODAL: Lightbox dla obrazów w pełnym rozmiarze (Pkt 6.k.iv) -->
	<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content bg-coffee-dark border-coffee text-light">
				<div class="modal-header bg-coffee-surface border-coffee py-2">
					<h6 class="modal-title mb-0" id="lightboxTitle">Podgląd obrazu</h6>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
				</div>
				<div class="modal-body p-2 text-center bg-black">
					<img id="lightboxImg" src="" alt="Podgląd" class="img-fluid rounded" style="max-height: 80vh;">
				</div>
				<div class="modal-footer border-coffee py-2 justify-content-between">
					<span id="lightboxInfo" class="small text-muted"></span>
					<a id="lightboxDownloadBtn" href="#" class="btn btn-sm btn-outline-info">
						<img src="media/menu_icons/download.svg" height="14" class="filter-white me-1" alt=""> Pobierz oryginał
					</a>
				</div>
			</div>
		</div>
	</div>

	<?php include 'footer.php'; ?>

	<script type="text/javascript">
		// Funkcja otwierająca obraz w pełnym rozmiarze w oknie Lightbox (Pkt 6.k.iv)
		function openLightbox(imageUrl, title) {
			$('#lightboxImg').attr('src', imageUrl);
			$('#lightboxTitle').text(title);
			$('#lightboxDownloadBtn').attr('href', imageUrl.replace('view.php', 'download.php'));
			const myModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
			myModal.show();
		}
	</script>
</body>
</html>
<?php
/**
 * ====================================================================================================
 * MATERIAŁ TEORETYCZNY I OPRACOWANIE WYNIKÓW BADAWCZYCH DO SPRAWOZDANIA (ZADANIE 5 - MyCloud)
 * Politechnika Bydgoska - Wydział Telekomunikacji, Informatyki i Elektrotechniki
 * Kierunek: Informatyka Stosowana | Przedmiot: Technologie Multimedialne
 * Autor: Jakub Baka
 * ====================================================================================================
 * 
 * 1. CHARAKTERYSTYKA I PORÓWNANIE USŁUG DYSKÓW SIECIOWYCH (STORAGE CLOUD)
 * ----------------------------------------------------------------------------------------------------
 * - Apple iCloud: Ściśle zintegrowany z ekosystemem iOS/macOS. Oferuje bezpłatnie 5 GB przestrzeni.
 *   Wykorzystuje zaawansowaną synchronizację iCloud Drive, synchronizację zdjęć, kopie urządzeń oraz pęk kluczy.
 *   Formaty multimedialne są zoptymalizowane pod kodeki Apple (HEIC, Apple ProRes, ALAC).
 * - Google One (Dysk Google): Zapewnia 15 GB darmowej przestrzeni dzielonej pomiędzy Gmail, Google Drive i Zdjęcia.
 *   Charakteryzuje się rozbudowanym API (Google Drive REST API), możliwością współedycji dokumentów w czasie
 *   rzeczywistym (Google Docs/Sheets) oraz bardzo wydajnym mechanizmem transkodowania wideo w locie.
 * - Microsoft OneDrive: Standardowo 5 GB w wersji bezpłatnej, zintegrowany z pakietem Microsoft 365.
 *   Wyróżnia się technologią "Files On-Demand" (pliki na żądanie) w systemie Windows oraz zaawansowanym
 *   bezpieczeństwem (Personal Vault z autoryzacją wieloskładnikową 2FA).
 * - Amazon AWS (Amazon S3 / WorkDocs): Profesjonalna usługa obiektowego przechowywania danych (Object Storage)
 *   o gwarantowanej trwałości 99.999999999% (11 dziewiątek). Umożliwia wersjonowanie obiektów, polityki
 *   cyklu życia (przenoszenie do tańszych warstw S3 Glacier) oraz precyzyjną kontrolę dostępu za pomocą IAM i ACL.
 * 
 * 2. ARCHITEKTURA I DZIAŁANIE ROZPROSZONEGO SYSTEMU KONTROLI WERSJI GIT
 * ----------------------------------------------------------------------------------------------------
 * - System Git został stworzony w 2005 roku przez Linusa Torvaldsa na potrzeby rozwoju jądra systemu Linux.
 * - Istotą architektury rozproszonej (DVCS) jest fakt, że każdy węzeł (lokalny komputer programisty) posiada
 *   pełną kopię repozytorium wraz z całą jego historią zmian, gałęziami (branches) i tagami.
 * - Różnica względem systemów scentralizowanych (SVN, CVS):
 *   W SVN awaria centralnego serwera paraliżuje pracę zespołu i uniemożliwia zatwierdzanie zmian. W Git każda
 *   operacja (commit, log, diff, branch) wykonuje się lokalnie w ułamku sekundy, a synchronizacja z serwerem
 *   zewnętrznym (remote - np. GitHub, GitLab) odbywa się poprzez operacje 'push' i 'fetch/pull'.
 * - Bezpieczeństwo integralności: Git identyfikuje każdy obiekt (commit, blob, tree) za pomocą sumy kontrolnej
 *   SHA-1 / SHA-256. Uniemożliwia to niezauważoną modyfikację zawartości lub historii projektu.
 * 
 * 3. MECHANIZMY PRZESYŁANIA PLIKÓW W PHP I PROTOKOLE HTTP (RFC 1867)
 * ----------------------------------------------------------------------------------------------------
 * - Formularz wysyłający pliki musi posiadać metodę POST oraz atrybut enctype="multipart/form-data".
 *   Wówczas ciało żądania HTTP dzielone jest na sekcje rozdzielone unikalnym separatorem (boundary).
 * - Serwer HTTP i interpreter PHP odbierają strumień danych i automatycznie zapisują przesłany plik w katalogu
 *   tymczasowym systemu operacyjnego (np. /tmp) pod losową nazwą, wypełniając superglobalną tablicę $_FILES.
 * - Przeniesienie pliku do miejsca docelowego realizowane jest funkcją move_uploaded_file(), która weryfikuje,
 *   czy dany plik rzeczywiście pochodzi z mechanizmu przesyłania HTTP, zapobiegając atakom typu LFI.
 * - Kluczowe dyrektywy konfiguracyjne php.ini:
 *   a) file_uploads = On (włączenie obsługi uploadu)
 *   b) upload_max_filesize (maksymalny rozmiar pojedynczego przesyłanego pliku, np. 64M)
 *   c) post_max_size (maksymalny rozmiar całego żądania POST - musi być większy niż upload_max_filesize)
 *   d) max_file_uploads (maksymalna liczba jednocześnie przesyłanych plików w jednym żądaniu)
 *   e) memory_limit oraz max_execution_time (zasoby pamięciowe i czasowe interpretera PHP)
 * 
 * 4. BEZPIECZEŃSTWO SYSTEMU PLIKÓW: ZAPOBIEGANIE ATAKOM DIRECTORY TRAVERSAL (PATH TRAVERSAL)
 * ----------------------------------------------------------------------------------------------------
 * - Zagrożenie: Użytkownik przesyła zmanipulowaną ścieżkę w parametrze (np. ../../../etc/passwd lub ../inny_user).
 * - Ochrona zaimplementowana w aplikacji MyCloud:
 *   a) Funkcja resolveSafeUserPath(): rygorystyczne oczyszczanie nazw z sekwencji '../', '..\' i separatorów katalogów.
 *   b) Walidacja za pomocą funkcji realpath() interpretera PHP: pobierana jest rzeczywista, kanoniczna ścieżka
 *      w systemie plików, a następnie weryfikowane jest, czy zaczyna się ona od ścieżki katalogu domowego użytkownika.
 *   c) Izolacja uprawnień: użytkownik sesyjny posiada dostęp wyłącznie do drzewa katalogów o korzeniu w /z5/{username}/.
 * 
 * 5. PROBLEM BUFOROWANIA PRZEZ PROGRAMY FTP I PRZEGLĄDARKI INTERNETOWE
 * ----------------------------------------------------------------------------------------------------
 * - Klienci FTP (np. Total Commander): Przy pierwszym wejściu buforują listę zawartości (NLST/LIST). Wychodzą
 *   z założenia, że tylko dany klient modyfikuje strukturę plików. Aby wymusić odświeżenie, należy zastosować
 *   ponowne nawiązanie połączenia lub skrót klawiszowy ponownego odczytu (R/F5).
 * - Przeglądarki internetowe: Buforują zasoby graficzne i multimedialne. W aplikacji zaimplementowano nagłówki
 *   Cache-Control, unikalne sygnatury czasowe zapytań AJAX (_t=timestamp) oraz strumieniowanie przez view.php
 *   z obsługą zapytań częściowych (HTTP 206 Partial Content i nagłówków Range).
 * ====================================================================================================
 */
?>
