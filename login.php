<?php
declare(strict_types=1);

require __DIR__ . '/app.php';
$db = database();
$currentUser = current_user($db);

if ($currentUser !== null) {
    redirect('dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Formularz wygasł lub jest nieprawidłowy. Odśwież stronę i spróbuj ponownie.';
    } else {
        $email = strtolower(trim(post_string('email')));
        $password = post_string('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Podaj poprawny adres e-mail.';
        }

        if ($password === '' || strlen($password) > 255) {
            $errors[] = 'Podaj hasło.';
        }

        if ($errors === []) {
            $statement = $db->prepare('SELECT id, name, surname, email, password, role, active FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!$user || (int) $user['active'] !== 1 || !password_verify($password, $user['password'])) {
                $errors[] = 'Nieprawidłowy e-mail lub hasło.';
            } else {
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $update = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $update->execute([
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $user['id'],
                    ]);
                }

                sign_in($user);
                set_flash('success', 'Zalogowano pomyślnie.');
                redirect('dashboard.php');
            }
        }
    }
}

$pageTitle = 'Logowanie';
require __DIR__ . '/partials/header.php';
?>
<main class="auth-page">
  <section class="auth-card">
    <p class="eyebrow">Witaj ponownie</p>
    <h1>Zaloguj się</h1>
    <p class="auth-intro">Zaloguj się, aby przejść do swojego panelu.</p>
    <?php render_form_errors($errors); ?>
    <form method="post" action="login.php" class="account-form">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <label for="email">Adres e-mail</label>
      <input id="email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= escape($email) ?>">
      <label for="password">Hasło</label>
      <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required>
      <button class="button button-primary" type="submit">Zaloguj się</button>
    </form>
    <p class="auth-switch">Nie masz jeszcze konta? <a href="register.php">Zarejestruj się</a></p>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
