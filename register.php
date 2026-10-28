<?php
declare(strict_types=1);

require __DIR__ . '/app.php';
$db = database();
$currentUser = current_user($db);

if ($currentUser !== null) {
    redirect('dashboard.php');
}

$errors = [];
$name = '';
$surname = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        http_response_code(400);
        $errors[] = 'Formularz wygasł lub jest nieprawidłowy. Odśwież stronę i spróbuj ponownie.';
    } else {
        $name = trim(post_string('name'));
        $surname = trim(post_string('surname'));
        $email = strtolower(trim(post_string('email')));
        $phone = trim(post_string('phone'));
        $password = post_string('password');
        $passwordConfirmation = post_string('password_confirmation');

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
        if (strlen($password) < 10 || strlen($password) > 72) {
            $errors[] = 'Hasło musi mieć od 10 do 72 bajtów.';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'Podane hasła nie są identyczne.';
        }

        if ($errors === []) {
            $statement = $db->prepare(
                'INSERT INTO users (name, surname, email, password, phone, role) VALUES (:name, :surname, :email, :password, :phone, :role)'
            );

            try {
                $statement->execute([
                    'name' => $name,
                    'surname' => $surname,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => 'client',
                ]);
            } catch (PDOException $exception) {
                if (($exception->errorInfo[1] ?? null) !== 1062) {
                    throw $exception;
                }

                $errors[] = 'Konto z tym adresem e-mail już istnieje.';
            }

            if ($errors === []) {
                sign_in(['id' => (int) $db->lastInsertId()]);
                set_flash('success', 'Konto zostało utworzone. Możesz już korzystać z serwisu.');
                redirect('dashboard.php');
            }
        }
    }
}

$pageTitle = 'Rejestracja';
require __DIR__ . '/partials/header.php';
?>
<main class="auth-page">
  <section class="auth-card">
    <p class="eyebrow">Dołącz do nas</p>
    <h1>Utwórz konto</h1>
    <p class="auth-intro">Załóż konto klienta, aby zarządzać swoimi wizytami.</p>
    <?php render_form_errors($errors); ?>
    <form method="post" action="register.php" class="account-form">
      <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
      <div class="form-row">
        <div><label for="name">Imię</label><input id="name" name="name" autocomplete="given-name" maxlength="80" required value="<?= escape($name) ?>"></div>
        <div><label for="surname">Nazwisko</label><input id="surname" name="surname" autocomplete="family-name" maxlength="100" required value="<?= escape($surname) ?>"></div>
      </div>
      <label for="email">Adres e-mail</label>
      <input id="email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= escape($email) ?>">
      <label for="phone">Telefon <span class="optional">(opcjonalnie)</span></label>
      <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="<?= escape($phone) ?>">
      <label for="password">Hasło <span class="optional">(od 10 do 72 bajtów)</span></label>
      <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>
      <label for="password_confirmation">Powtórz hasło</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" maxlength="72" required>
      <button class="button button-primary" type="submit">Utwórz konto</button>
    </form>
    <p class="auth-switch">Masz już konto? <a href="login.php">Zaloguj się</a></p>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
