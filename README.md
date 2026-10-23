# Rezerwuj - system rezerwacji usług

**Autor:** Dominik Siwiński.

Projekt semestralny: aplikacja internetowa do przeglądania oferty salonu i rezerwacji usług. Aktualny zakres obejmuje responsywną stronę oferty, relacyjny model danych i instalator bazy; logowanie oraz zapis rezerwacji z formularza będą rozwijane w kolejnych etapach.

## Technologie

- PHP 8+
- HTML5 i CSS3
- MySQL 8.0.16+ lub MariaDB 10.2.1+
- Git i GitHub

## Struktura projektu

- `index.php` - strona główna i oferta usług pobierana z bazy (z demonstracyjną ofertą zastępczą, gdy baza jest niedostępna)
- `style.css` - responsywne style strony
- `config.php` - konfiguracja połączenia z bazą z użyciem zmiennych środowiskowych
- `setup.php` - instalator bazy danych i danych startowych
- `database/database.sql` - schemat relacyjny oraz dane demonstracyjne
- `database/ERD.md` - diagram relacji encji
- `database/README.md` - opis tabel i zasad modelu danych

## Uruchomienie

Wymagane są PHP 8+ z rozszerzeniem `pdo_mysql` oraz MySQL 8.0.16+ lub MariaDB 10.2.1+.

1. Uruchom serwer MySQL/MariaDB i przygotuj konto mające uprawnienia do utworzenia bazy danych.
2. Ustaw zmienne środowiskowe połączenia (przykład dla PowerShell):

   ```powershell
   $env:DB_HOST = "127.0.0.1"
   $env:DB_PORT = "3307"
   $env:DB_NAME = "rezerwuj"
   $env:DB_USER = "root"
   $env:DB_PASSWORD = "twoje-haslo"
   ```

   Domyślny port projektu to `3307`; hasło nie jest przechowywane w repozytorium.
3. W katalogu głównym projektu uruchom `php -S localhost:8000`.
4. Otwórz `http://localhost:8000/setup.php`, aby utworzyć bazę i dane demonstracyjne.
5. Otwórz `http://localhost:8000/index.php`.

Instalator utworzy bazę `rezerwuj`, tabele, relacje, indeksy oraz dane startowe. Można uruchomić go ponownie; tabele i dane demonstracyjne nie będą dublowane.

## Demonstracyjne konta w bazie

Po uruchomieniu instalatora baza zawiera konta testowe:

| Rola | E-mail | Hasło |
|---|---|---|
| Administrator | `admin@rezerwuj.local` | `Rezerwuj123!` |
| Pracownik | `anna@rezerwuj.local` | `Rezerwuj123!` |
| Pracownik | `piotr@rezerwuj.local` | `Rezerwuj123!` |

Hasła są zapisane w bazie jako hashe. Konta i hasła są przeznaczone wyłącznie do lokalnych danych demonstracyjnych. Formularz logowania nie jest jeszcze częścią aktualnej wersji.

## Baza danych

Schemat, opis relacji i diagram ERD są dostępne w katalogu [`database`](database/README.md). Schemat obejmuje użytkowników, kategorie i usługi, pracowników, dostępność oraz rezerwacje. Ograniczenia bazy pilnują poprawnych czasów, unikalnych przypisań i zgodności usługi z pracownikiem.
