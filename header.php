<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$username = $_SESSION['username'] ?? '';
$avatar = $_SESSION['avatar'] ?? 'media/default_avatar.png';
$isAdmin = ($username === 'admin');

if (empty($avatar) || !file_exists(__DIR__ . '/' . $avatar)) {
    $avatar = 'media/default_avatar.png';
}
?>
<header>
<div class="fixed-top">
<nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
	<div class="container-fluid">
		<a class="navbar-brand d-flex align-items-center" href="index.php">
			<img src="media/menu_icons/cloud.svg" height="22" class="me-2 filter-white" alt="">
			MyCloud - Dysk Sieciowy
		</a>
	
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main_nav" aria-controls="main_nav" aria-expanded="false" aria-label="Nawigacja">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="collapse navbar-collapse" id="main_nav">
			<ul class="navbar-nav me-auto">
				<?php if ($isLoggedIn): ?>
					<li class="nav-item">
						<a class="nav-link d-flex align-items-center" href="index.php">
							<img src="media/menu_icons/cloud.svg" height="16" class="me-1 filter-white" alt="">
							Mój Dysk
						</a>
					</li>
				<?php endif; ?>
			</ul>

			<ul class="navbar-nav ms-auto align-items-center">
				<?php if ($isLoggedIn): ?>
					<li class="nav-item d-flex align-items-center me-3 my-1 my-lg-0 text-light">
						<img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="avatar-nav me-2">
						<span class="small fw-semibold">
							<?= htmlspecialchars($username) ?>
							<?php if ($isAdmin): ?>
								<span class="badge bg-danger ms-1">ADMIN</span>
							<?php endif; ?>
						</span>
					</li>
					<li class="nav-item my-1 my-lg-0">
						<a class="btn btn-outline-light btn-sm d-flex align-items-center" href="logout.php">
							<img src="media/menu_icons/sign-out.svg" height="16" class="me-1 filter-white" alt="">
							Wyloguj
						</a>
					</li>
				<?php else: ?>
					<li class="nav-item">
						<a class="nav-link d-flex align-items-center" href="login.php">
							<img src="media/menu_icons/box-arrow-in-right.svg" height="18" class="me-1 filter-white" alt="">
							Logowanie
						</a>
					</li>
					<li class="nav-item">
						<a class="nav-link d-flex align-items-center" href="rejestruj.php">
							<img src="media/menu_icons/person-plus.svg" height="18" class="me-1 filter-white" alt="">
							Rejestracja
						</a>
					</li>
				<?php endif; ?>
			</ul>
		</div> 
	</div> 
</nav>		
</div>
</header>
