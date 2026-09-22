<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$services = [];
$employees = [];

try {
  $db = new PDO(
    "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
    $config['user'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
  $services = $db->query('SELECT id, name, description, duration, price FROM services WHERE active = 1 ORDER BY id')->fetchAll();
  $employees = $db->query("SELECT e.id, CONCAT(u.name, ' ', u.surname) AS full_name FROM employees e JOIN users u ON u.id = e.user_id WHERE e.active = 1 AND u.active = 1 ORDER BY u.surname, u.name")->fetchAll();
} catch (PDOException $exception) {
  $services = [
    ['id' => 1, 'name' => 'Strzyżenie damskie', 'description' => 'Cięcie, modelowanie i pielęgnacja dopasowana do Twoich potrzeb.', 'duration' => 60, 'price' => 80],
    ['id' => 2, 'name' => 'Strzyżenie męskie', 'description' => 'Precyzyjne cięcie i stylizacja w wygodnym dla Ciebie terminie.', 'duration' => 30, 'price' => 50],
    ['id' => 3, 'name' => 'Koloryzacja', 'description' => 'Odśwież kolor lub zmień swój look z pomocą specjalisty.', 'duration' => 120, 'price' => 150],
  ];
  $employees = [
    ['id' => 1, 'full_name' => 'Dowolny dostępny'],
    ['id' => 2, 'full_name' => 'Anna Kowalska'],
    ['id' => 3, 'full_name' => 'Piotr Nowak'],
  ];
}

$serviceIcons = ['orange', 'blue', 'green'];
$serviceSymbols = ['&#10024;', '&#10022;', '&#10047;'];
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Prosty system rezerwacji usług">
  <title>Rezerwuj | System rezerwacji usług</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand" href="#start" aria-label="Rezerwuj - strona główna">
        <span class="brand-mark">R</span>
        <span>rezerwuj<span class="brand-dot">.</span></span>
      </a>
      <nav class="main-nav" aria-label="Główna nawigacja">
        <a href="#uslugi">Usługi</a>
        <a href="#jak-dziala">Jak to działa</a>
        <a href="#role">Strefy użytkownika</a>
        <a class="nav-button" href="#rezerwacja">Zarezerwuj termin</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero" id="start">
      <div class="container hero-grid">
        <div class="hero-copy">
          <p class="eyebrow">Prosto. Wygodnie. Na czas.</p>
          <h1>Umów usługę,<br><span>która pasuje do Ciebie.</span></h1>
          <p class="hero-lead">Wybierz usługę, pracownika i dogodny termin. Wszystko w jednym miejscu, bez telefonów i zbędnego czekania.</p>
          <div class="hero-actions">
            <a class="button button-primary" href="#rezerwacja">Umów wizytę <span aria-hidden="true">&#8594;</span></a>
            <a class="text-link" href="#uslugi">Zobacz ofertę</a>
          </div>
          <div class="hero-note"><span class="status-dot"></span> Dostępne terminy aktualizowane na bieżąco</div>
        </div>
        <div class="hero-panel" aria-label="Najbliższa dostępność">
          <div class="panel-topline">
            <span>NAJBLIŻSZE TERMINY</span>
            <span class="live-label"><span class="status-dot"></span> LIVE</span>
          </div>
          <div class="calendar-card">
            <div class="calendar-header">
              <div><strong>Wrzesień 2026</strong><small>Wybierz dogodny dzień</small></div>
              <div class="calendar-arrows" aria-hidden="true"><span>&#8592;</span><span>&#8594;</span></div>
            </div>
            <div class="weekdays"><span>Pn</span><span>Wt</span><span>Śr</span><span>Cz</span><span>Pt</span><span>Sb</span><span>Nd</span></div>
            <div class="days" aria-label="Kalendarz września 2026">
              <span class="muted">31</span><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span>
              <span>7</span><span>8</span><span>9</span><span class="selected">10</span><span>11</span><span>12</span><span>13</span>
              <span>14</span><span>15</span><span>16</span><span>17</span><span>18</span><span>19</span><span>20</span>
              <span>21</span><span>22</span><span>23</span><span>24</span><span>25</span><span>26</span><span>27</span>
            </div>
          </div>
          <div class="next-slot"><span class="slot-icon">&#10003;</span><div><small>NAJWCZEŚNIEJSZY WOLNY TERMIN</small><strong>Dzisiaj, 14:30</strong></div><span class="arrow">&#8594;</span></div>
        </div>
      </div>
    </section>

    <section class="section services" id="uslugi">
      <div class="container">
        <div class="section-heading"><div><p class="eyebrow">Oferta usług</p><h2>Wybierz coś dla siebie</h2></div><a class="text-link" href="#rezerwacja">Zobacz wszystkie <span aria-hidden="true">&#8594;</span></a></div>
        <div class="service-grid">
          <?php foreach ($services as $index => $service): ?>
            <article class="service-card<?= $index === 0 ? ' featured' : '' ?>">
              <div class="service-icon <?= $serviceIcons[$index % count($serviceIcons)] ?>"><?= $serviceSymbols[$index % count($serviceSymbols)] ?></div>
              <?php if ($index === 0): ?><span class="service-tag">POPULARNE</span><?php endif; ?>
              <h3><?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p><?= htmlspecialchars($service['description'], ENT_QUOTES, 'UTF-8') ?></p>
              <div class="service-meta"><strong>od <?= number_format((float) $service['price'], 0, ',', ' ') ?> zł</strong><span><?= (int) $service['duration'] ?> min</span></div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section process" id="jak-dziala">
      <div class="container process-grid">
        <div><p class="eyebrow">Szybko i bez komplikacji</p><h2>Rezerwacja w kilku krokach</h2><p class="section-copy">System pokazuje tylko dostępne terminy. Dzięki temu wybierasz usługę, pracownika i godzinę bez ryzyka kolizji.</p><a class="button button-dark" href="#rezerwacja">Przejdź do rezerwacji <span aria-hidden="true">&#8594;</span></a></div>
        <ol class="steps"><li><span>01</span><div><h3>Wybierz usługę</h3><p>Sprawdź opis, cenę i czas trwania.</p></div></li><li><span>02</span><div><h3>Wybierz pracownika</h3><p>Zobacz osoby, które wykonują daną usługę.</p></div></li><li><span>03</span><div><h3>Wybierz termin</h3><p>System uwzględni godziny pracy i zajęte wizyty.</p></div></li><li><span>04</span><div><h3>Potwierdź wizytę</h3><p>Gotowe. Rezerwacja trafia do Twojego panelu.</p></div></li></ol>
      </div>
    </section>

    <section class="section roles" id="role">
      <div class="container"><div class="section-heading"><div><p class="eyebrow">Dla każdego użytkownika</p><h2>Jedna aplikacja, trzy strefy</h2></div></div><div class="role-grid"><article><span class="role-number">01</span><h3>Klient</h3><p>Przegląda ofertę, tworzy rezerwacje, zarządza swoim profilem i anuluje przyszłe wizyty.</p><a href="#rezerwacja">Umów wizytę &#8594;</a></article><article><span class="role-number">02</span><h3>Pracownik</h3><p>Widok dzisiejszych i przyszłych wizyt, szczegóły rezerwacji oraz zmiana statusu obsługi.</p><a href="#role">Panel pracownika &#8594;</a></article><article><span class="role-number">03</span><h3>Administrator</h3><p>Zarządzanie usługami, pracownikami, użytkownikami, dostępnością i wszystkimi rezerwacjami.</p><a href="#role">Panel administratora &#8594;</a></article></div></div>
    </section>

    <section class="section booking" id="rezerwacja">
      <div class="container booking-grid"><div><p class="eyebrow">Zacznij tutaj</p><h2>Zarezerwuj swój termin</h2><p>Wybierz usługę, pracownika i dogodną godzinę. Zajmie Ci to tylko chwilę.</p></div><form class="booking-form" action="#" method="post"><label>Usługa<select name="service"><option>Wybierz usługę</option><?php foreach ($services as $service): ?><option value="<?= (int) $service['id'] ?>"><?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Pracownik<select name="employee"><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><div class="form-row"><label>Data<input type="date" name="date"></label><label>Godzina<select name="time"><option>Wybierz godzinę</option><option>14:30</option><option>15:30</option><option>16:30</option></select></label></div><label>Uwagi <span class="optional">(opcjonalnie)</span><textarea name="message" rows="3" placeholder="Napisz, jeśli chcesz coś dodać..."></textarea></label><button class="button button-primary" type="submit">Sprawdź dostępność <span aria-hidden="true">&#8594;</span></button></form></div>
    </section>
  </main>

  <footer class="site-footer"><div class="container footer-wrap"><div><a class="brand" href="#start"><span class="brand-mark">R</span><span>rezerwuj<span class="brand-dot">.</span></span></a><p>Prosty system rezerwacji usług.</p></div><div class="footer-links"><a href="#uslugi">Usługi</a><a href="#jak-dziala">Jak to działa</a><a href="#role">Dla użytkowników</a><a href="#rezerwacja">Kontakt</a></div></div><div class="container footer-bottom"><span>&copy; 2026 Rezerwuj</span><span>Wszystkie prawa zastrzeżone</span></div></footer>
</body>
</html>
