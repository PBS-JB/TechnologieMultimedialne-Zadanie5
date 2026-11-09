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
	<meta name="description" content="Rejestracja nowego użytkownika MyCloud">
	<title>MyCloud - Rejestracja</title>
	
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
						<h2 class="h3 fw-bold mb-1">Rejestracja w MyCloud</h2>
						<p class="text-muted small">Utwórz konto, aby otrzymać własny dysk sieciowy</p>
					</div>

					<?php if (isset($_GET['error'])): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<strong>Błąd rejestracji!</strong> 
							<?php
								$err = $_GET['error'];
								if ($err === 'empty_fields') echo 'Wszystkie wymagane pola muszą zostać wypełnione.';
								elseif ($err === 'passwords_mismatch') echo 'Wprowadzone hasła nie są identyczne.';
								elseif ($err === 'user_exists') echo 'Użytkownik o podanym loginie już istnieje w systemie.';
								elseif ($err === 'invalid_username') echo 'Login może zawierać wyłącznie litery, cyfry, znak podkreślenia i myślnik (3-24 znaki).';
								elseif ($err === 'file_too_large') echo 'Plik awatara przekracza dopuszczalny limit 2MB.';
								elseif ($err === 'invalid_file') echo 'Dozwolone są wyłącznie pliki graficzne (JPG, PNG, GIF, WEBP).';
								else echo 'Wystąpił nieoczekiwany błąd podczas rejestracji.';
							?>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
						</div>
					<?php endif; ?>

					<form action="add.php" method="post" enctype="multipart/form-data" id="registerForm" onsubmit="return validateRegisterForm()">
						<div class="mb-3">
							<label for="user" class="form-label">Nazwa użytkownika (Login)</label>
							<div class="input-group">
								<span class="input-group-text"><img src="media/menu_icons/person.svg" width="16" height="16" alt=""></span>
								<input type="text" class="form-control" id="user" name="user" required autofocus 
									   pattern="[a-zA-Z0-9_\-]{3,24}" 
									   title="Login musi zawierać od 3 do 24 znaków: litery, cyfry, podkreślenie lub myślnik"
									   placeholder="np. test1">
							</div>
							<div class="form-text text-muted small">Posłuży również jako nazwa Twojego katalogu macierzystego na dysku.</div>
						</div>

						<div class="mb-3">
							<label for="pass1" class="form-label">Hasło</label>
							<div class="input-group">
								<span class="input-group-text"><img src="media/menu_icons/gear.svg" width="16" height="16" alt=""></span>
								<input type="password" class="form-control" id="pass1" name="pass1" required minlength="4" placeholder="Wprowadź hasło">
							</div>
						</div>

						<div class="mb-3">
							<label for="pass2" class="form-label">Powtórz hasło</label>
							<div class="input-group">
								<span class="input-group-text"><img src="media/menu_icons/check.svg" width="16" height="16" alt=""></span>
								<input type="password" class="form-control" id="pass2" name="pass2" required minlength="4" placeholder="Powtórz hasło">
							</div>
							<div id="passFeedback" class="small mt-1"></div>
						</div>

						<div class="mb-4">
							<label for="avatar" class="form-label">Awatar profilu (opcjonalny)</label>
							<div class="d-flex align-items-center mb-2">
								<img id="avatarPreview" src="media/default_avatar.png" alt="Podgląd awatara" class="avatar-preview-img me-3">
								<input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewAvatar(this)">
							</div>
						</div>

						<button type="submit" class="btn btn-coffee w-100 py-2 mb-3 fw-semibold">Utwórz konto i dysk</button>
						
						<div class="text-center">
							<span class="text-muted small">Posiadasz już konto?</span>
							<a href="login.php" class="text-accent small fw-semibold text-decoration-none ms-1">Zaloguj się</a>
						</div>
					</form>
				</div>
			</div>
		</section>
	</main>

	<?php include 'footer.php'; ?>

	<script type="text/javascript">
		function previewAvatar(input) {
			if (input.files && input.files[0]) {
				const reader = new FileReader();
				reader.onload = function(e) {
					$('#avatarPreview').attr('src', e.target.result);
				}
				reader.readAsDataURL(input.files[0]);
			}
		}

		$('#pass1, #pass2').on('keyup', function() {
			const p1 = $('#pass1').val();
			const p2 = $('#pass2').val();
			const $fb = $('#passFeedback');
			
			if (p2.length === 0) {
				$fb.text('').removeClass('text-success text-danger');
			} else if (p1 === p2) {
				$fb.text('Hasła są zgodne').removeClass('text-danger').addClass('text-success');
			} else {
				$fb.text('Hasła nie są zgodne!').removeClass('text-success').addClass('text-danger');
			}
		});

		function validateRegisterForm() {
			const p1 = $('#pass1').val();
			const p2 = $('#pass2').val();
			if (p1 !== p2) {
				alert('Wprowadzone hasła muszą być identyczne!');
				return false;
			}
			return true;
		}
	</script>
</body>
</html>
