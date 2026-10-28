# Baza danych

`database.sql` zawiera relacyjny schemat systemu rezerwacji: użytkowników i role, kategorie, usługi, pracowników, relację N:M pracowników z usługami, dostępność oraz rezerwacje. Zawiera również dane demonstracyjne, klucze obce, indeksy i ograniczenia integralności.

Diagram ERD znajduje się w pliku [`ERD.md`](ERD.md).

Najprościej uruchomić instalację przez `setup.php` z katalogu głównego projektu. Skrypt utworzy bazę, tabele i dane startowe.

## Model danych

- `users` przechowuje wspólne dane logowania i role (`client`, `employee`, `admin`).
- `employees` rozszerza dane użytkownika o informacje pracownicze.
- `service_categories` grupuje `services`.
- `employee_services` łączy pracowników i usługi relacją N:M.
- `employee_availability` przechowuje tygodniowe godziny pracy albo wyjątki dla konkretnej daty. Dzień tygodnia: 1 = poniedziałek, 7 = niedziela. Jeśli dla daty zdefiniowano wyjątek, zastępuje on grafik tygodniowy.
- `reservations` łączy klienta, pracownika i usługę; klucz obcy złożony wymaga, aby pracownik był przypisany do rezerwowanej usługi.

Klienci tworzą rezerwacje z panelu. Dostępne godziny są wyliczane z grafiku w odstępach 30-minutowych, z uwzględnieniem czasu trwania usługi i kolizji z rezerwacjami, których status nie jest `cancelled`. Przed zapisem aplikacja blokuje rekord pracownika i sprawdza termin ponownie.

Schemat korzysta z ograniczeń `CHECK`, dlatego wymaga MySQL 8.0.16+ lub MariaDB 10.2.1+.
