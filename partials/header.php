<?php
declare(strict_types=1);

$flash = take_flash();
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="System rezerwacji usług">
  <title><?= escape($pageTitle) ?> | Rezerwuj</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand" href="index.php" aria-label="Rezerwuj - strona główna">
        <span class="brand-mark">R</span>
        <span>rezerwuj<span class="brand-dot">.</span></span>
      </a>
      <nav class="main-nav" aria-label="Główna nawigacja">
        <a href="index.php#uslugi">Usługi</a>
        <?php if ($currentUser !== null): ?>
          <a href="dashboard.php">Panel</a>
          <a href="profile.php">Mój profil</a>
          <form action="logout.php" method="post" class="nav-logout">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <button type="submit" class="nav-button">Wyloguj</button>
          </form>
        <?php else: ?>
          <a href="login.php">Logowanie</a>
          <a class="nav-button" href="register.php">Załóż konto</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <?php if ($flash !== null): ?>
    <div class="container">
      <p class="flash-message <?= escape($flash['type'] ?? 'info') ?>" role="status"><?= escape($flash['message'] ?? '') ?></p>
    </div>
  <?php endif; ?>
