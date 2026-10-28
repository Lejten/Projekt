<?php
declare(strict_types=1);

require __DIR__ . '/app.php';
$db = database();
$currentUser = require_user($db);
$errors = [];
$success = '';
$isProfilePost = $_SERVER['REQUEST_METHOD'] === 'POST' && post_string('action') === 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Formularz wygasł lub jest nieprawidłowy. Odśwież stronę i spróbuj ponownie.';
    } else {
        $action = post_string('action');

        if ($action === 'profile') {
            $name = trim(post_string('name'));
            $surname = trim(post_string('surname'));
            $email = strtolower(trim(post_string('email')));
            $phone = trim(post_string('phone'));

            if ($name === '' || strlen($name) > 80) {
                $errors[] = 'Imię jest wymagane i może mieć maksymalnie 80 znaków.';
            }
            if ($surname === '' || strlen($surname) > 100) {
                $errors[] = 'Nazwisko jest wymagane i może mieć maksymalnie 100 znaków.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                $errors[] = 'Podaj poprawny adres e-mail (maksymalnie 190 znaków).';
            }
            if (strlen($phone) > 30) {
                $errors[] = 'Numer telefonu może mieć maksymalnie 30 znaków.';
            }

            if ($errors === []) {
                $statement = $db->prepare('UPDATE users SET name = :name, surname = :surname, email = :email, phone = :phone WHERE id = :id');
                try {
                    $statement->execute([
                        'name' => $name,
                        'surname' => $surname,
                        'email' => $email,
                        'phone' => $phone !== '' ? $phone : null,
                        'id' => $currentUser['id'],
                    ]);
                    $currentUser['name'] = $name;
                    $currentUser['surname'] = $surname;
                    $currentUser['email'] = $email;
                    $currentUser['phone'] = $phone !== '' ? $phone : null;
                    $success = 'Dane profilu zostały zapisane.';
                } catch (PDOException $exception) {
                    if (($exception->errorInfo[1] ?? null) !== 1062) {
                        throw $exception;
                    }
                    $errors[] = 'Konto z tym adresem e-mail już istnieje.';
                }
            }
        } elseif ($action === 'password') {
            $oldPassword = post_string('old_password');
            $newPassword = post_string('new_password');
            $confirmation = post_string('password_confirmation');

            $statement = $db->prepare('SELECT password FROM users WHERE id = :id');
            $statement->execute(['id' => $currentUser['id']]);
            $passwordHash = $statement->fetchColumn();

            if (!is_string($passwordHash) || !password_verify($oldPassword, $passwordHash)) {
                $errors[] = 'Aktualne hasło jest nieprawidłowe.';
            }
            if (strlen($newPassword) < 10 || strlen($newPassword) > 72) {
                $errors[] = 'Nowe hasło musi mieć od 10 do 72 bajtów.';
            }
            if ($newPassword !== $confirmation) {
                $errors[] = 'Nowe hasła nie są identyczne.';
            }

            if ($errors === []) {
                $statement = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
                $statement->execute([
                    'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'id' => $currentUser['id'],
                ]);
                session_regenerate_id(true);
                $success = 'Hasło zostało zmienione.';
            }
        } else {
            http_response_code(400);
            $errors[] = 'Nieznana operacja formularza.';
        }
    }
}

$pageTitle = 'Mój profil';
require __DIR__ . '/partials/header.php';
?>
<main class="auth-page">
  <section class="auth-card profile-card">
    <p class="eyebrow">Ustawienia konta</p>
    <h1>Mój profil</h1>
    <?php render_form_errors($errors); ?>
    <?php if ($success !== ''): ?><p class="flash-message success" role="status"><?= escape($success) ?></p><?php endif; ?>
    <form method="post" action="profile.php" class="account-form">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <input type="hidden" name="action" value="profile">
      <div class="form-row">
        <div><label for="name">Imię</label><input id="name" name="name" maxlength="80" required value="<?= escape($isProfilePost ? ($_POST['name'] ?? '') : $currentUser['name']) ?>"></div>
        <div><label for="surname">Nazwisko</label><input id="surname" name="surname" maxlength="100" required value="<?= escape($isProfilePost ? ($_POST['surname'] ?? '') : $currentUser['surname']) ?>"></div>
      </div>
      <label for="email">Adres e-mail</label>
      <input id="email" name="email" type="email" maxlength="190" required value="<?= escape($isProfilePost ? ($_POST['email'] ?? '') : $currentUser['email']) ?>">
      <label for="phone">Telefon</label>
      <input id="phone" name="phone" type="tel" maxlength="30" value="<?= escape($isProfilePost ? ($_POST['phone'] ?? '') : ($currentUser['phone'] ?? '')) ?>">
      <button class="button button-primary" type="submit">Zapisz profil</button>
    </form>
    <hr class="form-divider">
    <h2>Zmiana hasła</h2>
    <form method="post" action="profile.php" class="account-form">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <input type="hidden" name="action" value="password">
      <label for="old_password">Aktualne hasło</label>
      <input id="old_password" name="old_password" type="password" autocomplete="current-password" maxlength="255" required>
      <label for="new_password">Nowe hasło <span class="optional">(od 10 do 72 bajtów)</span></label>
      <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>
      <label for="password_confirmation">Powtórz nowe hasło</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>
      <button class="button button-dark" type="submit">Zmień hasło</button>
    </form>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
