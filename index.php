<?php
declare(strict_types=1);
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

$currentSubdir = trim($_GET['dir'] ?? '');
$currentSubdir = str_replace(['..', '/', '\\'], '', $currentSubdir);

$isInsideSubdir = !empty($currentSubdir);
$currentDir = $userHomeDir;

if ($isInsideSubdir) {
    $resolvedDir = resolveSafeUserPath($username, $currentSubdir);
    if ($resolvedDir !== null && is_dir($resolvedDir)) {
        $currentDir = $resolvedDir;
    } else {
        header('Location: index.php?error=invalid_dir');
        exit();
    }
}

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

$stats = getUserStorageStats($username);

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
	<title>Mój Dysk (MyCloud)</title>
	
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">
	<link rel="stylesheet" type="text/css" href="twoj_css.css">
	
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

			<div class="row align-items-center mb-4 g-3">
				<div class="col-12 col-md-auto me-auto">
					<div class="d-flex align-items-center">
						<div class="cloud-header-icon me-3">
							<img src="media/menu_icons/cloud.svg" width="36" height="36" class="filter-accent" alt="">
						</div>
						<div>
							<h1 class="h3 fw-bold mb-0 text-light">Mój Dysk Sieciowy</h1>
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

				<div class="col-12 col-md-auto d-flex flex-wrap gap-2">
					
					<?php if ($isInsideSubdir): ?>
						<a href="index.php" class="btn btn-outline-warning btn-sm d-flex align-items-center" title="Wróć do katalogu macierzystego">
							<img src="media/menu_icons/arrow-up.svg" height="16" class="me-1 filter-white" alt="">
							Poziom wyżej
						</a>
					<?php endif; ?>

					<?php if (!$isInsideSubdir): ?>
						<button type="button" class="btn btn-outline-coffee btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#newFolderModal">
							<img src="media/menu_icons/folder-plus.svg" height="16" class="me-1 filter-accent" alt="">
							Nowy katalog
						</button>
					<?php endif; ?>

					<button type="button" class="btn btn-coffee btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#uploadModal">
						<img src="media/menu_icons/upload.svg" height="16" class="me-1 filter-white" alt="">
						Wyślij plik
					</button>

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

			<?php if (!empty($subdirectories)): ?>
				<div class="mb-4">
					<h2 class="h6 text-muted text-uppercase fw-bold letter-spacing-1 mb-3">Katalogi</h2>
					<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
						<?php foreach ($subdirectories as $dir): ?>
							<div class="col">
								<div class="card bg-coffee-dark border-coffee card-hover h-100 shadow-sm">
									<div class="card-body p-3 d-flex align-items-center justify-content-between">
										<a href="index.php?dir=<?= urlencode($dir['name']) ?>" class="d-flex align-items-center text-decoration-none text-light text-truncate me-2">
											<div class="folder-icon-box me-3">
												<img src="media/menu_icons/folder.svg" width="28" height="28" class="filter-accent" alt="">
											</div>
											<div class="text-truncate">
												<div class="fw-semibold text-truncate"><?= htmlspecialchars($dir['name']) ?></div>
												<small class="text-muted"><?= $dir['item_count'] ?> elementów</small>
											</div>
										</a>
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

			<div class="mb-4">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h2 class="h6 text-muted text-uppercase fw-bold letter-spacing-1 mb-0">
						Pliki <?= $isInsideSubdir ? 'w katalogu: /' . htmlspecialchars($currentSubdir) : 'w katalogu głównym' ?>
					</h2>
					<span class="badge bg-secondary"><?= count($files) ?> plików</span>
				</div>

				<?php if (empty($files) && empty($subdirectories)): ?>
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

					<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3">
						<?php foreach ($files as $file): 
							$viewUrl = 'view.php?file=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
							$downloadUrl = 'download.php?file=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
							$deleteUrl = 'delete.php?item=' . urlencode($file['name']) . ($isInsideSubdir ? '&dir=' . urlencode($currentSubdir) : '');
						?>
							<div class="col">
								<div class="card bg-coffee-dark border-coffee card-hover h-100 shadow-sm file-card">
									
									<div class="file-card-preview bg-black d-flex align-items-center justify-content-center position-relative overflow-hidden">
										
										<?php if ($file['category'] === 'image'): ?>
											<img src="<?= $viewUrl ?>" alt="<?= htmlspecialchars($file['name']) ?>" class="img-fluid file-img-thumb" onclick="openLightbox('<?= $viewUrl ?>', '<?= htmlspecialchars(addslashes($file['name'])) ?>')" title="Kliknij, aby otworzyć obraz w pełnym rozmiarze">
											<div class="preview-overlay">
												<span class="badge bg-dark bg-opacity-75 text-light small">Powiększ</span>
											</div>

										<?php elseif ($file['category'] === 'video'): ?>
											<video controls preload="metadata" class="w-100 file-video-player">
												<source src="<?= $viewUrl ?>" type="video/<?= $file['ext'] === 'mp4' ? 'mp4' : 'webm' ?>">
												Twoja przeglądarka nie wspiera tagu wideo.
											</video>

										<?php elseif ($file['category'] === 'audio'): ?>
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
											<div class="text-center p-3">
												<div class="doc-icon-badge bg-danger text-light mb-2">PDF</div>
												<a href="<?= $viewUrl ?>" target="_blank" class="btn btn-sm btn-outline-light">
													Otwórz dokument
												</a>
											</div>

										<?php else: ?>
											<div class="text-center p-3">
												<span class="badge bg-secondary fs-6 text-uppercase mb-2"><?= htmlspecialchars($file['ext']) ?></span>
												<div class="text-muted small">Plik binarny</div>
											</div>
										<?php endif; ?>

									</div>

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
