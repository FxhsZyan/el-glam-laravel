-- Clears demo activity: bookings and their payments, expenses, purchase orders, stock movements,
-- inventory items, and failed sign-in records.
-- Branches, treatments, user accounts and their permissions are kept.
-- Afterwards, import schema.sql again to restore the starting stock.
USE el_glam;

DELETE FROM payments;
DELETE FROM bookings;
DELETE FROM package_redemptions;
DELETE FROM customer_packages;
DELETE FROM expenses;
DELETE FROM expense_audit;
DELETE FROM purchase_orders;
DELETE FROM stock_movements;
DELETE FROM inventory_items;
DELETE FROM login_attempts;
ALTER TABLE bookings AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE package_redemptions AUTO_INCREMENT = 1;
ALTER TABLE customer_packages AUTO_INCREMENT = 1;
ALTER TABLE expenses AUTO_INCREMENT = 1;
ALTER TABLE purchase_orders AUTO_INCREMENT = 1;
ALTER TABLE stock_movements AUTO_INCREMENT = 1;
ALTER TABLE inventory_items AUTO_INCREMENT = 1;
