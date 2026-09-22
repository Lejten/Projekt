# Rezerwuj - system rezerwacji usług

## Struktura

- `index.php` - strona główna z ofertą usług pobieraną z bazy
- `style.css` - responsywne style strony
- `config.php` - konfiguracja połączenia z MySQL/MariaDB
- `setup.php` - instalator bazy danych i danych startowych
- `database/database.sql` - schemat relacyjnej bazy danych

## Uruchomienie

Wymagane są PHP 8+ z rozszerzeniem `pdo_mysql` oraz MySQL lub MariaDB.

1. Uruchom serwer MySQL/MariaDB.
2. Ustaw dane połączenia w `config.php` albo zmiennymi `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Domyślny port projektu to `3307`.
3. Uruchom serwer poleceniem `php -S localhost:8000`.
4. Otwórz `http://localhost:8000/setup.php`, a następnie `http://localhost:8000/index.php`.

Instalator utworzy bazę `rezerwuj`, tabele, relacje, indeksy oraz dane startowe. Można uruchomić go ponownie bez tworzenia duplikatów.