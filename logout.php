<?php
declare(strict_types=1);

require __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Wylogowanie wymaga wysłania formularza.');
}

if (!csrf_is_valid()) {
    http_response_code(400);
    exit('Formularz wygasł lub jest nieprawidłowy. Odśwież stronę i spróbuj ponownie.');
}

sign_out();
redirect('index.php');
