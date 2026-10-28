<?php
declare(strict_types=1);

require __DIR__ . '/app.php';
$db = database();
$currentUser = require_user($db);
$bookingErrors = [];
$bookingServiceId = 0;
$bookingEmployeeId = 0;
$bookingDate = date('Y-m-d', strtotime('+1 day'));
$bookingTime = '';
$bookingComment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_string('action') === 'create_reservation') {
    require_role($currentUser, ['client']);

    $bookingServiceId = filter_var(post_string('service_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $bookingEmployeeId = filter_var(post_string('employee_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $bookingDate = post_string('reservation_date');
    $bookingTime = post_string('start_time');
    $bookingComment = trim(post_string('comment'));

    if (!csrf_is_valid()) {
        http_response_code(400);
        $bookingErrors[] = 'Formularz wygasł lub jest nieprawidłowy. Odśwież stronę i spróbuj ponownie.';
    } elseif ($bookingServiceId === 0 || $bookingEmployeeId === 0) {
        $bookingErrors[] = 'Wybierz usługę i pracownika.';
    } elseif (strlen($bookingComment) > 2000) {
        $bookingErrors[] = 'Uwagi mogą mieć maksymalnie 2000 znaków.';
    } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $bookingTime)) {
        $bookingErrors[] = 'Wybierz godzinę z listy dostępnych terminów.';
    } else {
        try {
            $db->beginTransaction();

            $employeeLock = $db->prepare(
                'SELECT e.id
                 FROM employees e
                 JOIN users u ON u.id = e.user_id
                 JOIN employee_services es ON es.employee_id = e.id
                 JOIN services s ON s.id = es.service_id
                 WHERE e.id = :employee_id AND s.id = :service_id
                   AND e.active = 1 AND u.active = 1 AND s.active = 1
                 FOR UPDATE'
            );
            $employeeLock->execute([
                'employee_id' => $bookingEmployeeId,
                'service_id' => $bookingServiceId,
            ]);

            if ($employeeLock->fetchColumn() === false) {
                $bookingErrors[] = 'Wybrana usługa lub pracownik nie jest już dostępny.';
            } else {
                $availableSlots = available_booking_slots($db, $bookingEmployeeId, $bookingServiceId, $bookingDate);
                if (!in_array($bookingTime, $availableSlots, true)) {
                    $bookingErrors[] = 'Ten termin nie jest już dostępny. Wybierz inny wolny termin.';
                } else {
                    $durationStatement = $db->prepare('SELECT duration FROM services WHERE id = :service_id AND active = 1');
                    $durationStatement->execute(['service_id' => $bookingServiceId]);
                    $duration = $durationStatement->fetchColumn();
                    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $bookingDate . ' ' . $bookingTime);

                    if ($duration === false || $start === false) {
                        $bookingErrors[] = 'Nie udało się potwierdzić wybranego terminu.';
                    } else {
                        $endTime = $start->modify('+' . (int) $duration . ' minutes')->format('H:i:s');
                        $insert = $db->prepare(
                            "INSERT INTO reservations (user_id, employee_id, service_id, reservation_date, start_time, end_time, status, comment)
                             VALUES (:user_id, :employee_id, :service_id, :reservation_date, :start_time, :end_time, 'pending', :comment)"
                        );
                        $insert->execute([
                            'user_id' => $currentUser['id'],
                            'employee_id' => $bookingEmployeeId,
                            'service_id' => $bookingServiceId,
                            'reservation_date' => $bookingDate,
                            'start_time' => $bookingTime . ':00',
                            'end_time' => $endTime,
                            'comment' => $bookingComment !== '' ? $bookingComment : null,
                        ]);
                    }
                }
            }

            if ($bookingErrors !== []) {
                $db->rollBack();
            } else {
                $db->commit();
                set_flash('success', 'Rezerwacja została utworzona i oczekuje na potwierdzenie.');
                redirect('dashboard.php');
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
}

$bookingServiceId = $bookingServiceId ?: (filter_var($_GET['service_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0);
$bookingEmployeeId = $bookingEmployeeId ?: (filter_var($_GET['employee_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $requestedDate = $_GET['reservation_date'] ?? null;
    if (is_string($requestedDate) && $requestedDate !== '') {
        $bookingDate = $requestedDate;
    }
}

$services = $db->query('SELECT id, name, duration, price FROM services WHERE active = 1 ORDER BY name')->fetchAll();
$employees = [];
$slots = [];

if ($currentUser['role'] === 'client' && $bookingServiceId > 0) {
    $employeeStatement = $db->prepare(
        'SELECT e.id, CONCAT(u.name, \' \', u.surname) AS full_name
         FROM employees e
         JOIN users u ON u.id = e.user_id
         JOIN employee_services es ON es.employee_id = e.id
         JOIN services s ON s.id = es.service_id
         WHERE es.service_id = :service_id AND e.active = 1 AND u.active = 1 AND s.active = 1
         ORDER BY u.surname, u.name'
    );
    $employeeStatement->execute(['service_id' => $bookingServiceId]);
    $employees = $employeeStatement->fetchAll();

    $validEmployeeIds = array_map(static fn (array $employee): int => (int) $employee['id'], $employees);
    if ($bookingEmployeeId > 0 && in_array($bookingEmployeeId, $validEmployeeIds, true)) {
        $slots = available_booking_slots($db, $bookingEmployeeId, $bookingServiceId, $bookingDate);
    }
}

$reservationSql = <<<'SQL'
SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.status, r.comment,
       s.name AS service_name, s.price,
       CONCAT(employee_user.name, ' ', employee_user.surname) AS employee_name,
       CONCAT(client.name, ' ', client.surname) AS client_name
FROM reservations r
JOIN services s ON s.id = r.service_id
JOIN employees e ON e.id = r.employee_id
JOIN users employee_user ON employee_user.id = e.user_id
JOIN users client ON client.id = r.user_id
SQL;

if ($currentUser['role'] === 'client') {
    $statement = $db->prepare($reservationSql . ' WHERE r.user_id = :user_id ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 50');
    $statement->execute(['user_id' => $currentUser['id']]);
    $reservations = $statement->fetchAll();
} elseif ($currentUser['role'] === 'employee') {
    $statement = $db->prepare($reservationSql . ' WHERE e.user_id = :user_id ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 50');
    $statement->execute(['user_id' => $currentUser['id']]);
    $reservations = $statement->fetchAll();
} else {
    require_role($currentUser, ['admin']);
    $reservations = $db->query($reservationSql . ' ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 100')->fetchAll();
    $statistics = $db->query(
        'SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM services WHERE active = 1) AS services, (SELECT COUNT(*) FROM reservations) AS reservations'
    )->fetch();
}

$pageTitle = 'Panel użytkownika';
require __DIR__ . '/partials/header.php';
?>
<main class="section dashboard">
  <div class="container">
    <p class="eyebrow"><?= escape(match ($currentUser['role']) {
        'admin' => 'Panel administratora',
        'employee' => 'Panel pracownika',
        default => 'Panel klienta',
    }) ?></p>
    <h1>Witaj, <?= escape($currentUser['name']) ?>!</h1>
    <p class="dashboard-intro">Zalogowano jako <?= escape($currentUser['email']) ?>. <a href="profile.php">Edytuj profil</a>.</p>

    <?php if ($currentUser['role'] === 'client'): ?>
      <section class="dashboard-section booking-section" id="nowa-rezerwacja">
        <h2>Zarezerwuj wizytę</h2>
        <p class="booking-intro">Wybierz usługę, pracownika i datę, aby sprawdzić wolne godziny. Terminy są dostępne w odstępach 30-minutowych.</p>
        <?php render_form_errors($bookingErrors); ?>
        <form method="get" action="dashboard.php#nowa-rezerwacja" class="booking-form reservation-search">
          <div class="form-row">
            <label for="booking-service">Usługa
              <select id="booking-service" name="service_id" required>
                <option value="">Wybierz usługę</option>
                <?php foreach ($services as $service): ?>
                  <option value="<?= (int) $service['id'] ?>" <?= $bookingServiceId === (int) $service['id'] ? 'selected' : '' ?>><?= escape($service['name']) ?> (<?= (int) $service['duration'] ?> min, <?= number_format((float) $service['price'], 2, ',', ' ') ?> zł)</option>
                <?php endforeach; ?>
              </select>
            </label>
            <label for="booking-employee">Pracownik
              <select id="booking-employee" name="employee_id">
                <option value="">Wybierz pracownika</option>
                <?php foreach ($employees as $employee): ?>
                  <option value="<?= (int) $employee['id'] ?>" <?= $bookingEmployeeId === (int) $employee['id'] ? 'selected' : '' ?>><?= escape($employee['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label for="booking-date">Data
              <input id="booking-date" name="reservation_date" type="date" min="<?= escape(date('Y-m-d')) ?>" value="<?= escape($bookingDate) ?>" required>
            </label>
          </div>
          <button class="button button-dark" type="submit">Pokaż dostępne godziny</button>
        </form>

        <?php if ($bookingServiceId > 0 && $employees === []): ?>
          <p class="empty-state">Żaden aktywny pracownik nie wykonuje wybranej usługi.</p>
        <?php elseif ($bookingServiceId > 0 && $bookingEmployeeId === 0): ?>
          <p class="empty-state">Wybierz pracownika i ponownie pokaż dostępne godziny.</p>
        <?php endif; ?>

        <?php if ($bookingServiceId > 0 && $bookingEmployeeId > 0 && $slots !== []): ?>
          <form method="post" action="dashboard.php#nowa-rezerwacja" class="booking-form reservation-confirm">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_reservation">
            <input type="hidden" name="service_id" value="<?= $bookingServiceId ?>">
            <input type="hidden" name="employee_id" value="<?= $bookingEmployeeId ?>">
            <input type="hidden" name="reservation_date" value="<?= escape($bookingDate) ?>">
            <label for="booking-time">Wolna godzina
              <select id="booking-time" name="start_time" required>
                <option value="">Wybierz godzinę</option>
                <?php foreach ($slots as $slot): ?>
                  <option value="<?= escape($slot) ?>" <?= $bookingTime === $slot ? 'selected' : '' ?>><?= escape($slot) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label for="booking-comment">Uwagi <span class="optional">(opcjonalnie)</span>
              <textarea id="booking-comment" name="comment" rows="3" maxlength="2000"><?= escape($bookingComment) ?></textarea>
            </label>
            <button class="button button-primary" type="submit">Potwierdź rezerwację</button>
          </form>
        <?php elseif ($bookingServiceId > 0 && $bookingEmployeeId > 0): ?>
          <p class="empty-state">Brak wolnych terminów dla wybranej usługi, pracownika i daty. Wybierz inną datę.</p>
        <?php elseif ($services === []): ?>
          <p class="empty-state">Brak aktywnych usług. Spróbuj ponownie później.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($currentUser['role'] === 'admin'): ?>
      <div class="statistics-grid">
        <article><strong><?= (int) $statistics['users'] ?></strong><span>kont użytkowników</span></article>
        <article><strong><?= (int) $statistics['services'] ?></strong><span>aktywnych usług</span></article>
        <article><strong><?= (int) $statistics['reservations'] ?></strong><span>rezerwacji</span></article>
      </div>
    <?php endif; ?>

    <section class="dashboard-section">
      <h2><?= $currentUser['role'] === 'client' ? 'Moje rezerwacje' : 'Rezerwacje' ?></h2>
      <?php if ($reservations === []): ?>
        <p class="empty-state">Nie ma jeszcze rezerwacji do wyświetlenia.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Termin</th><th>Usługa</th><th>Pracownik</th><?php if ($currentUser['role'] !== 'client'): ?><th>Klient</th><?php endif; ?><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($reservations as $reservation): ?>
                <tr>
                  <td><?= escape($reservation['reservation_date']) ?><br><?= escape(substr($reservation['start_time'], 0, 5)) ?>–<?= escape(substr($reservation['end_time'], 0, 5)) ?></td>
                  <td><?= escape($reservation['service_name']) ?></td>
                  <td><?= escape($reservation['employee_name']) ?></td>
                  <?php if ($currentUser['role'] !== 'client'): ?><td><?= escape($reservation['client_name']) ?></td><?php endif; ?>
                  <td><span class="reservation-status"><?= escape(match ($reservation['status']) {
                      'pending' => 'Oczekująca',
                      'confirmed' => 'Potwierdzona',
                      'completed' => 'Zakończona',
                      'cancelled' => 'Anulowana',
                      default => $reservation['status'],
                  }) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
