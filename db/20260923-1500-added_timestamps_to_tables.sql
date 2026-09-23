-- ============================================================
--  MeraGullak migration: created_at + updated_at on every table
--  Run ONCE via phpMyAdmin Import with the `gullak` DB selected.
--  Re-runnable: safe to import twice (prints "already applied" notes).
--
--  Definitions match house style:
--    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
--    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
--               ON UPDATE CURRENT_TIMESTAMP  (MySQL maintains it; no PHP changes)
--  Existing rows get migration time for the new columns, except
--  wallets.created_at which is backfilled from its owner's
--  users.created_at (a wallet is born with its user).
--  price_history.recorded_at is untouched (domain market time).
-- ============================================================

USE gullak;

-- ---------- users: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: users.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- wallets: add created_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'wallets' AND COLUMN_NAME = 'created_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE wallets ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER balance',
  'SELECT "already applied: wallets.created_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- holdings: add created_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'holdings' AND COLUMN_NAME = 'created_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE holdings ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER invested',
  'SELECT "already applied: holdings.created_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- holdings: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'holdings' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE holdings ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: holdings.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- metal_prices: add created_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'metal_prices' AND COLUMN_NAME = 'created_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE metal_prices ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sell_rate',
  'SELECT "already applied: metal_prices.created_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- price_history: add created_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'price_history' AND COLUMN_NAME = 'created_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE price_history ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER recorded_at',
  'SELECT "already applied: price_history.created_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- price_history: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'price_history' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE price_history ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: price_history.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- transactions: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE transactions ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: transactions.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- sip_logs: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'sip_logs' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE sip_logs ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: sip_logs.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- bank_accounts: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'bank_accounts' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE bank_accounts ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: bank_accounts.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- withdrawals: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'withdrawals' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE withdrawals ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: withdrawals.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments: add updated_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: payments.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- admins: add updated_at ----------SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE admins ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "already applied: admins.updated_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- settings: add created_at ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'created_at');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE settings ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP FIRST',
  'SELECT "already applied: settings.created_at" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- backfill: wallets are born with their users (idempotent) ----------
UPDATE wallets w JOIN users u ON u.id = w.user_id SET w.created_at = u.created_at;
