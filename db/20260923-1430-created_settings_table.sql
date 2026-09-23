-- ============================================================
--  MeraGullak migration: platform settings (spread %) store
--  Run ONCE via phpMyAdmin Import with the `gullak` DB selected.
--  Re-runnable: safe to import twice (prints "already applied" notes).
-- ============================================================

USE gullak;

-- 1) settings table (key-value store for admin-editable platform values)
SET @has_tbl := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'settings');
SET @sql := IF(@has_tbl = 0,
  'CREATE TABLE settings (
     setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
     setting_value VARCHAR(64)  NOT NULL,
     updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB',
  'SELECT "already applied: settings table exists" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) seed spread defaults (matches config.php; never overwrites existing rows)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('gold_buy_pct',   '1.5'),
  ('gold_sell_pct',  '1.5'),
  ('silver_buy_pct', '4.0'),
  ('silver_sell_pct','4.0');
