<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
date_default_timezone_set('Europe/Warsaw');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function database(): PDO
{
    static $connection;
    global $config;

    if (!$connection instanceof PDO) {
        $connection = new PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
            $config['user'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $connection;
}

function escape(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_string(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}

function redirect(string $location): void
{
    header('Location: ' . $location, true, 303);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function current_user(PDO $db): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId)) {
        return null;
    }

    $statement = $db->prepare('SELECT id, name, surname, email, phone, role, active FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if (!$user || (int) $user['active'] !== 1) {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        return null;
    }

    return $user;
}

function sign_in(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function sign_out(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function require_user(PDO $db): array
{
    $user = current_user($db);
    if ($user === null) {
        set_flash('error', 'Zaloguj się, aby otworzyć tę stronę.');
        redirect('login.php');
    }

    header('Cache-Control: no-store, private');
    return $user;
}

function require_role(array $user, array $roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Brak dostępu';
        $currentUser = $user;
        require __DIR__ . '/partials/header.php';
        echo '<main class="auth-page"><section class="auth-card"><p class="eyebrow">Błąd 403</p><h1>Nie masz dostępu do tej strony</h1><p>Twoje konto nie ma uprawnień wymaganych do wyświetlenia tej zawartości.</p><a class="button button-dark" href="dashboard.php">Wróć do panelu</a></section></main>';
        require __DIR__ . '/partials/footer.php';
        exit;
    }
}

function render_form_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }

    echo '<div class="form-errors" role="alert"><p>Popraw poniższe błędy:</p><ul>';
    foreach ($errors as $error) {
        echo '<li>' . escape($error) . '</li>';
    }
    echo '</ul></div>';
}

function available_booking_slots(PDO $db, int $employeeId, int $serviceId, string $date): array
{
    $bookingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($bookingDate === false || $bookingDate->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
        return [];
    }

    $serviceStatement = $db->prepare(
        'SELECT s.duration
         FROM services s
         JOIN employee_services es ON es.service_id = s.id
         JOIN employees e ON e.id = es.employee_id
         JOIN users u ON u.id = e.user_id
         WHERE s.id = :service_id AND e.id = :employee_id
           AND s.active = 1 AND e.active = 1 AND u.active = 1'
    );
    $serviceStatement->execute(['service_id' => $serviceId, 'employee_id' => $employeeId]);
    $duration = $serviceStatement->fetchColumn();

    if ($duration === false) {
        return [];
    }

    $availabilityStatement = $db->prepare(
        'SELECT start_time, end_time
         FROM employee_availability
         WHERE employee_id = :employee_id AND available_date = :available_date
         ORDER BY start_time'
    );
    $availabilityStatement->execute(['employee_id' => $employeeId, 'available_date' => $date]);
    $availability = $availabilityStatement->fetchAll();

    if ($availability === []) {
        $availabilityStatement = $db->prepare(
            'SELECT start_time, end_time
             FROM employee_availability
             WHERE employee_id = :employee_id AND available_date IS NULL AND day_of_week = :day_of_week
             ORDER BY start_time'
        );
        $availabilityStatement->execute([
            'employee_id' => $employeeId,
            'day_of_week' => (int) $bookingDate->format('N'),
        ]);
        $availability = $availabilityStatement->fetchAll();
    }

    $reservationsStatement = $db->prepare(
        "SELECT start_time, end_time
         FROM reservations
         WHERE employee_id = :employee_id AND reservation_date = :reservation_date
           AND status <> 'cancelled'"
    );
    $reservationsStatement->execute(['employee_id' => $employeeId, 'reservation_date' => $date]);
    $reservations = $reservationsStatement->fetchAll();
    $occupiedRanges = [];

    foreach ($reservations as $reservation) {
        $start = strtotime($date . ' ' . $reservation['start_time']);
        $end = strtotime($date . ' ' . $reservation['end_time']);
        if ($start !== false && $end !== false) {
            $occupiedRanges[] = [$start, $end];
        }
    }

    $slots = [];
    $durationSeconds = (int) $duration * 60;

    foreach ($availability as $schedule) {
        $shiftStart = strtotime($date . ' ' . $schedule['start_time']);
        $shiftEnd = strtotime($date . ' ' . $schedule['end_time']);
        if ($shiftStart === false || $shiftEnd === false) {
            continue;
        }

        for ($start = $shiftStart; $start + $durationSeconds <= $shiftEnd; $start += 30 * 60) {
            $end = $start + $durationSeconds;
            if ($start <= time()) {
                continue;
            }

            $overlaps = false;
            foreach ($occupiedRanges as [$occupiedStart, $occupiedEnd]) {
                if ($start < $occupiedEnd && $end > $occupiedStart) {
                    $overlaps = true;
                    break;
                }
            }

            if (!$overlaps) {
                $slots[date('H:i', $start)] = true;
            }
        }
    }

    $slotTimes = array_keys($slots);
    sort($slotTimes);

    return $slotTimes;
}
