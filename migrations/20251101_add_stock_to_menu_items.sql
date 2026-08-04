-- Migration: Add stock column to menu_items
-- Run this on your MySQL database used by the app (e.g., via phpMyAdmin or mysql CLI)

ALTER TABLE `menu_items`
ADD COLUMN `stock` INT NOT NULL DEFAULT 0;

-- Optional: set stock to 0 for unavailable items
UPDATE `menu_items` SET `stock` = 0 WHERE `is_available` = 0;

-- If you prefer to initialize existing items to a default stock, e.g., 50:
-- UPDATE `menu_items` SET `stock` = 50 WHERE `stock` = 0;
