-- EL Glamorous Face and Body Clinic booking system schema (MariaDB 10.4 / MySQL 8).
-- Safe to re-run: tables are created only if missing and seed rows use INSERT IGNORE.

CREATE DATABASE IF NOT EXISTS el_glam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE el_glam;

CREATE TABLE IF NOT EXISTS branches (
  id          VARCHAR(30)  NOT NULL PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL UNIQUE,
  area        VARCHAR(80)  NOT NULL,
  address     VARCHAR(255) NOT NULL,
  phones      VARCHAR(255) NOT NULL,
  hours       VARCHAR(120) NOT NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  mobile        VARCHAR(20)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
  job_title     VARCHAR(80)  NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  branch_id     VARCHAR(30)  NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;
-- Adds the job title column to databases created before it existed.
ALTER TABLE users ADD COLUMN IF NOT EXISTS job_title VARCHAR(80) NULL AFTER role;
-- Deactivated accounts can't sign in, and any open session ends on its next request.
ALTER TABLE users ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER job_title;

CREATE TABLE IF NOT EXISTS services (
  id           VARCHAR(40)  NOT NULL PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  category     ENUM('Face','Body','Hair','Nails') NOT NULL,
  duration_min SMALLINT UNSIGNED NULL,
  price        DECIMAL(10,2) NULL,
  active       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference        VARCHAR(20)  NOT NULL UNIQUE,
  user_id          INT UNSIGNED NULL,
  customer_name    VARCHAR(120) NOT NULL,
  mobile           VARCHAR(20)  NOT NULL,
  branch_id        VARCHAR(30)  NOT NULL,
  service_category ENUM('Face','Body','Hair','Nails') NOT NULL,
  service_id       VARCHAR(40)  NULL,
  booking_date     DATE         NOT NULL,
  booking_time     TIME         NOT NULL,
  status           ENUM('Pending','Confirmed','Completed','Cancelled','No-show') NOT NULL DEFAULT 'Pending',
  source           ENUM('Online','Walk-in') NOT NULL DEFAULT 'Online',
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- 1 while the booking holds its slot, NULL once cancelled/no-show (NULLs never collide in a unique index).
  active_slot      TINYINT AS (IF(status IN ('Cancelled','No-show'), NULL, 1)) PERSISTENT,
  UNIQUE KEY uniq_branch_slot (branch_id, booking_date, booking_time, active_slot),
  KEY idx_branch_date (branch_id, booking_date),
  CONSTRAINT fk_bookings_user    FOREIGN KEY (user_id)    REFERENCES users(id),
  CONSTRAINT fk_bookings_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id),
  CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  branch_id     VARCHAR(30)  NOT NULL,
  name          VARCHAR(120) NOT NULL,
  category      VARCHAR(60)  NOT NULL,
  unit          VARCHAR(30)  NOT NULL DEFAULT 'units',
  qty           INT UNSIGNED NOT NULL DEFAULT 0,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_branch_item (branch_id, name),
  CONSTRAINT fk_inventory_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_movements (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_id    INT UNSIGNED NOT NULL,
  branch_id  VARCHAR(30)  NOT NULL,
  type       ENUM('Stock In','Stock Out','Adjustment') NOT NULL,
  qty        INT          NOT NULL,
  note       VARCHAR(255) NULL,
  user_id    INT UNSIGNED NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_moves_item   FOREIGN KEY (item_id)   REFERENCES inventory_items(id),
  CONSTRAINT fk_moves_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_moves_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB;

-- Extra areas an individual account may open, on top of what its role allows (admins get all of them).
-- Keys: customers.view, sales, reports.month_end, employees.view, expenses.daily, expenses.po
-- Grant or revoke with database/permissions.php.
CREATE TABLE IF NOT EXISTS user_permissions (
  user_id    INT UNSIGNED NOT NULL,
  permission VARCHAR(40)  NOT NULL,
  PRIMARY KEY (user_id, permission),
  CONSTRAINT fk_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Payment taken when a booking is completed (one per booking). These make up the sales figures.
CREATE TABLE IF NOT EXISTS payments (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED  NOT NULL UNIQUE,
  branch_id   VARCHAR(30)   NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  method      ENUM('Cash','GCash','Maya','Card','Bank transfer') NOT NULL,
  service_staff_id INT UNSIGNED NULL,
  commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  card_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  note        VARCHAR(255)  NULL,
  received_by INT UNSIGNED  NULL,
  paid_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_branch_paid (branch_id, paid_at),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_branch  FOREIGN KEY (branch_id)   REFERENCES branches(id),
  CONSTRAINT fk_payments_user    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_service_staff FOREIGN KEY (service_staff_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS service_staff_id INT UNSIGNED NULL AFTER method;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER service_staff_id;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS card_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER commission_amount;
-- Add the staff foreign key to databases where the payments table predates these columns.
SET @payments_staff_fk_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
     AND COLUMN_NAME = 'service_staff_id' AND REFERENCED_TABLE_NAME = 'users') > 0,
  'SELECT 1',
  'ALTER TABLE payments ADD CONSTRAINT fk_payments_service_staff FOREIGN KEY (service_staff_id) REFERENCES users(id) ON DELETE SET NULL'
);
PREPARE payments_staff_fk_stmt FROM @payments_staff_fk_sql;
EXECUTE payments_staff_fk_stmt;
DEALLOCATE PREPARE payments_staff_fk_stmt;

CREATE TABLE IF NOT EXISTS service_packages (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL UNIQUE,
  service_id    VARCHAR(40) NULL,
  session_count SMALLINT UNSIGNED NOT NULL,
  price         DECIMAL(10,2) NOT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_packages_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_packages (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  package_id        INT UNSIGNED NOT NULL,
  branch_id         VARCHAR(30) NOT NULL,
  customer_name     VARCHAR(120) NOT NULL,
  mobile            VARCHAR(20) NOT NULL,
  sessions_total    SMALLINT UNSIGNED NOT NULL,
  sessions_remaining SMALLINT UNSIGNED NOT NULL,
  amount_paid       DECIMAL(10,2) NOT NULL,
  sold_by           INT UNSIGNED NULL,
  purchased_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  active            TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_customer_package_branch (branch_id, active, purchased_at),
  CONSTRAINT fk_customer_packages_plan FOREIGN KEY (package_id) REFERENCES service_packages(id),
  CONSTRAINT fk_customer_packages_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_customer_packages_sold_by FOREIGN KEY (sold_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS package_redemptions (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_package_id INT UNSIGNED NOT NULL,
  booking_id          INT UNSIGNED NULL,
  redeemed_by         INT UNSIGNED NULL,
  note                VARCHAR(255) NULL,
  redeemed_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_package_redemptions_date (customer_package_id, redeemed_at),
  CONSTRAINT fk_redemptions_package FOREIGN KEY (customer_package_id) REFERENCES customer_packages(id),
  CONSTRAINT fk_redemptions_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_redemptions_user FOREIGN KEY (redeemed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Day-to-day spending (supplies bought in cash, utilities, transport, ...).
CREATE TABLE IF NOT EXISTS expenses (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  branch_id    VARCHAR(30)   NOT NULL,
  expense_date DATE          NOT NULL,
  category     VARCHAR(60)   NOT NULL,
  description  VARCHAR(255)  NOT NULL,
  amount       DECIMAL(10,2) NOT NULL,
  recorded_by  INT UNSIGNED  NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_branch_date (branch_id, expense_date),
  CONSTRAINT fk_expenses_branch FOREIGN KEY (branch_id)   REFERENCES branches(id),
  CONSTRAINT fk_expenses_user   FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expense_audit (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  expense_id   INT UNSIGNED NOT NULL,
  action       ENUM('Updated','Deleted') NOT NULL,
  old_values   LONGTEXT NOT NULL,
  new_values   LONGTEXT NULL,
  changed_by   INT UNSIGNED NULL,
  changed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_expense_audit_date (changed_at),
  KEY idx_expense_audit_id (expense_id),
  CONSTRAINT fk_expense_audit_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Purchase orders placed with suppliers.
CREATE TABLE IF NOT EXISTS purchase_orders (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  po_number   VARCHAR(20)   NOT NULL UNIQUE,
  branch_id   VARCHAR(30)   NOT NULL,
  supplier    VARCHAR(120)  NOT NULL,
  description VARCHAR(255)  NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  order_date  DATE          NOT NULL,
  status      ENUM('Ordered','Received','Cancelled') NOT NULL DEFAULT 'Ordered',
  recorded_by INT UNSIGNED  NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_branch_date (branch_id, order_date),
  CONSTRAINT fk_po_branch FOREIGN KEY (branch_id)   REFERENCES branches(id),
  CONSTRAINT fk_po_user   FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Failed sign-ins, used to pause sign-in after repeated wrong passwords (see api/auth.php).
-- Kept in the database so clearing cookies doesn't reset the count. Rows older than a day are removed automatically.
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(190) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_time (email, attempted_at),
  KEY idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- Branch details as listed in the prototypes.
INSERT IGNORE INTO branches (id, name, area, address, phones, hours) VALUES
  ('canduman',  'Canduman',  'Mandaue City', '2/F Paradise Bldg., H. Abellana St., Canduman, Mandaue City, Philippines 6014.', '+63 945 377 9343', 'Monday to Sunday, 11:00 AM to 8:00 PM'),
  ('pajac',     'Pajac',     'Lapu-Lapu City', 'Helenville Apartment, Bankal Road, Barangay Pajac, Lapu-Lapu City, Cebu.', '(032) 326 4014, 0915 076 6914, 0956 279 6480', 'Hours to be confirmed'),
  ('cebu-city', 'Cebu City',    'Cebu City',      '8V6R+8VM, Don Gil Street, Cebu City, 6000 Cebu, Philippines.', '0999 325 7150, 0956 279 6480', 'Hours to be confirmed'),
  ('pusok',     'Pusok', 'Pusok, Lapu-Lapu City', 'To be confirmed', 'To be confirmed', 'To be confirmed');

-- The branch first entered as "Basak" is the Pajac branch. On databases created before the fix,
-- move anything recorded under the old id to Pajac, then remove the old branch.
-- (This runs before the inventory list below, so moved items aren't added twice.)
UPDATE users           SET branch_id = 'pajac' WHERE branch_id = 'basak';
UPDATE bookings        SET branch_id = 'pajac' WHERE branch_id = 'basak';
UPDATE inventory_items SET branch_id = 'pajac' WHERE branch_id = 'basak';
UPDATE stock_movements SET branch_id = 'pajac' WHERE branch_id = 'basak';
DELETE FROM branches WHERE id = 'basak';
-- Pajac's real address, replacing the Basak address carried over from the rename (only if it's still the old one).
UPDATE branches SET area = 'Lapu-Lapu City', address = 'Helenville Apartment, Bankal Road, Barangay Pajac, Lapu-Lapu City, Cebu.'
WHERE id = 'pajac' AND address LIKE '%Basak%';

-- Treatment list from the staff view. Prices are left empty until the clinic confirms them.
INSERT IGNORE INTO services (id, name, category) VALUES
  ('facial-signature', 'Signature Facial',      'Face'),
  ('facial-cleanse',   'Deep Cleanse Facial',   'Face'),
  ('facial-express',   'Express Glow Facial',   'Face'),
  ('body-massage',     'Relaxing Body Massage', 'Body'),
  ('body-scrub',       'Body Scrub',            'Body'),
  ('body-polish',      'Body Polish Ritual',    'Body'),
  ('haircut',          'Haircut & Blow-dry',    'Hair'),
  ('hair-treatment',   'Hair Treatment',        'Hair'),
  ('blowdry',          'Blow-dry Styling',      'Hair'),
  ('manicure',         'Classic Manicure',      'Nails'),
  ('pedicure',         'Classic Pedicure',      'Nails'),
  ('manipedi',         'Mani-Pedi',             'Nails');

-- Starting stock: the admin prototype's sample items plus the staff prototype's Canduman items.
INSERT IGNORE INTO inventory_items (branch_id, name, category, unit, qty, reorder_level) VALUES
  ('canduman',  'Gentle facial cleanser',    'Face',            'bottles', 4,  6),
  ('pajac',     'Hydrating sheet masks',     'Face',            'packs',   18, 10),
  ('cebu-city', 'Massage oil · lavender',    'Body',            'bottles', 3,  5),
  ('canduman',  'Nitrile gloves · medium',   'Clinic supplies', 'boxes',   2,  8),
  ('pajac',     'Hair treatment sachets',    'Hair',            'packs',   24, 10),
  ('cebu-city', 'Gel polish · rose',         'Nails',           'bottles', 5,  6),
  ('canduman',  'Disposable spa headbands',  'Clinic supplies', 'packs',   32, 12),
  ('pajac',     'Body scrub · sugar',        'Body',            'jars',    7,  4),
  ('canduman',  'Hydrating facial cleanser', 'Face',            'bottles', 8,  5),
  ('canduman',  'Collagen facial mask',      'Face',            'pieces',  4,  8),
  ('canduman',  'Massage oil',               'Body',            'bottles', 12, 4),
  ('canduman',  'Nail polish · rose',        'Nails',           'bottles', 2,  5);
