CREATE DATABASE IF NOT EXISTS `rezerwuj` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rezerwuj`;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  surname VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NULL,
  role ENUM('client', 'employee', 'admin') NOT NULL DEFAULT 'client',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS service_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  duration SMALLINT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_services (
  employee_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (employee_id, service_id),
  CONSTRAINT fk_employee_services_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_employee_services_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_availability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NULL,
  available_date DATE NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  CONSTRAINT fk_availability_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT chk_availability_day CHECK (day_of_week BETWEEN 1 AND 7 OR available_date IS NOT NULL)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  reservation_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  comment TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_reservations_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_reservations_service FOREIGN KEY (service_id) REFERENCES services(id),
  INDEX idx_reservations_employee_time (employee_id, reservation_date, start_time, end_time),
  INDEX idx_reservations_user (user_id)
) ENGINE=InnoDB;

INSERT INTO service_categories (name, description) VALUES
  ('Fryzjerstwo', 'Usługi cięcia, stylizacji i koloryzacji')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO users (name, surname, email, password, phone, role) VALUES
  ('Anna', 'Kowalska', 'anna@rezerwuj.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCqI1i7F7n6mO4s1QwG2', '500600700', 'employee'),
  ('Piotr', 'Nowak', 'piotr@rezerwuj.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCqI1i7F7n6mO4s1QwG2', '500600701', 'employee'),
  ('Administrator', 'Systemu', 'admin@rezerwuj.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCqI1i7F7n6mO4s1QwG2', NULL, 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name), surname = VALUES(surname);

INSERT INTO services (category_id, name, description, duration, price) SELECT id, 'Strzyżenie damskie', 'Cięcie, modelowanie i pielęgnacja dopasowana do Twoich potrzeb.', 60, 80.00 FROM service_categories WHERE name = 'Fryzjerstwo' AND NOT EXISTS (SELECT 1 FROM services WHERE name = 'Strzyżenie damskie');
INSERT INTO services (category_id, name, description, duration, price) SELECT id, 'Strzyżenie męskie', 'Precyzyjne cięcie i stylizacja w wygodnym dla Ciebie terminie.', 30, 50.00 FROM service_categories WHERE name = 'Fryzjerstwo' AND NOT EXISTS (SELECT 1 FROM services WHERE name = 'Strzyżenie męskie');
INSERT INTO services (category_id, name, description, duration, price) SELECT id, 'Koloryzacja', 'Odśwież kolor lub zmień swój look z pomocą specjalisty.', 120, 150.00 FROM service_categories WHERE name = 'Fryzjerstwo' AND NOT EXISTS (SELECT 1 FROM services WHERE name = 'Koloryzacja');

INSERT INTO employees (user_id, description) SELECT id, 'Specjalistka stylizacji i koloryzacji.' FROM users WHERE email = 'anna@rezerwuj.local' AND NOT EXISTS (SELECT 1 FROM employees e JOIN users u ON u.id = e.user_id WHERE u.email = 'anna@rezerwuj.local');
INSERT INTO employees (user_id, description) SELECT id, 'Specjalista strzyżeń damskich i męskich.' FROM users WHERE email = 'piotr@rezerwuj.local' AND NOT EXISTS (SELECT 1 FROM employees e JOIN users u ON u.id = e.user_id WHERE u.email = 'piotr@rezerwuj.local');

INSERT IGNORE INTO employee_services (employee_id, service_id) SELECT e.id, s.id FROM employees e CROSS JOIN services s;
INSERT IGNORE INTO employee_availability (employee_id, day_of_week, start_time, end_time) SELECT id, 1, '09:00:00', '17:00:00' FROM employees;
INSERT IGNORE INTO employee_availability (employee_id, day_of_week, start_time, end_time) SELECT id, 2, '09:00:00', '17:00:00' FROM employees;
INSERT IGNORE INTO employee_availability (employee_id, day_of_week, start_time, end_time) SELECT id, 3, '09:00:00', '17:00:00' FROM employees;
INSERT IGNORE INTO employee_availability (employee_id, day_of_week, start_time, end_time) SELECT id, 4, '09:00:00', '17:00:00' FROM employees;
INSERT IGNORE INTO employee_availability (employee_id, day_of_week, start_time, end_time) SELECT id, 5, '09:00:00', '17:00:00' FROM employees;
