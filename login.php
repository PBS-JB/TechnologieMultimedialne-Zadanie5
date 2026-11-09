<?php
declare(strict_types=1);
session_start();

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">  
	<meta name="description" content="Logowanie do dysku sieciowego MyCloud">
	<meta name="author" content="Jakub Baka">
	<title>MyCloud - Logowanie | Jakub Baka</title>
	
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
	<main> 
		<section class="sekcja1">	
			<div class="container">
				<div class="form-auth-card">
					<div class="text-center mb-4">
						<img src="media/menu_icons/cloud.svg" alt="MyCloud" width="48" height="48" class="mb-2 filter-accent">
						<h2 class="h3 fw-bold mb-1">Dysk Sieciowy MyCloud</h2>
						<p class="text-muted small">Logowanie do prywatnej chmury plików</p>
					</div>

					<?php if (isset($_GET['registered'])): ?>
						<div class="alert alert-success alert-dismissible fade show" role="alert">
							<strong>Sukces!</strong> Twoje konto zostało utworzone, a katalog macierzysty zainicjalizowany. Możesz się zalogować.
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
						</div>
					<?php endif; ?>

					<?php if (isset($_GET['logged_out'])): ?>
						<div class="alert alert-info alert-dismissible fade show" role="alert">
							Zostałeś pomyślnie wylogowany z dysku sieciowego.
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
						</div>
					<?php endif; ?>

					<?php if (isset($_GET['error'])): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<strong>Błąd logowania!</strong> Nieprawidłowy login lub hasło.
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
						</div>
					<?php endif; ?>

					<!-- Formularz logowania do portalu (Pkt 6.a) -->
					<form action="weryfikuj.php" method="post" id="loginForm">
						<div class="mb-3">
							<label for="user" class="form-label">Nazwa użytkownika (Login)</label>
							<div class="input-group">
								<span class="input-group-text"><img src="media/menu_icons/person.svg" width="16" height="16" alt=""></span>
								<input type="text" class="form-control" id="user" name="user" required autofocus placeholder="np. test1">
							</div>
						</div>

						<div class="mb-4">
							<label for="pass" class="form-label">Hasło</label>
							<div class="input-group">
								<span class="input-group-text"><img src="media/menu_icons/gear.svg" width="16" height="16" alt=""></span>
								<input type="password" class="form-control" id="pass" name="pass" required placeholder="Wprowadź hasło">
							</div>
						</div>

						<button type="submit" class="btn btn-coffee w-100 py-2 mb-3 fw-semibold">Zaloguj się do chmury</button>
						
						<div class="text-center">
							<span class="text-muted small">Nie posiadasz jeszcze konta?</span>
							<a href="rejestruj.php" class="text-accent small fw-semibold text-decoration-none ms-1">Zarejestruj się</a>
						</div>
					</form>
				</div>
			</div>
		</section>
	</main>

	<?php include 'footer.php'; ?>
</body>
</html>
