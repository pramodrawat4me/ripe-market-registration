-- Ripe Market database. Import: mysql -u root -p < ripe_market.sql
CREATE DATABASE IF NOT EXISTS ripe_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ripe_market;

CREATE TABLE IF NOT EXISTS registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(12) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(160) NOT NULL,
  address TEXT NOT NULL,
  prize VARCHAR(160) NOT NULL DEFAULT '',
  used ENUM('no','yes') NOT NULL DEFAULT 'no',
  spins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  spun_at TIMESTAMP NULL DEFAULT NULL,
  INDEX (phone), INDEX (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wheel_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sort_order TINYINT UNSIGNED NOT NULL,
  prize VARCHAR(160) NOT NULL,
  chance DECIMAL(5,2) NOT NULL,
  retry TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

DELETE FROM wheel_slots;
INSERT INTO wheel_slots (sort_order, prize, chance, retry) VALUES
(1,'Goodie Bag',20,0),(2,'Try Again',15,1),(3,'Two Brothers Merchandise',23,0),
(4,'Discount 5% off',10,0),(5,'Better luck next time',15,0),
(6,'10 AED off on your Purchase',7,0),(7,'Discount 10% off',5,0),(8,'Jackpot',5,0);
