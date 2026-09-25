-- ============================================================
--  MeraGullak migration: full ICICI payment audit trail
--  Run ONCE via phpMyAdmin Import with the `gullak` DB selected.
--  Re-runnable: safe to import twice (prints "already applied" notes).
--
--  Why: money disputes need forensics, not just outcomes. Stores the
--  bank's payment-method details + the exact raw payloads per attempt.
--  All columns NULL: old rows are untouched and stay valid.
-- ============================================================

USE gullak;

-- ---------- payments.payment_mode ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_mode');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN payment_mode VARCHAR(12) NULL AFTER payment_id',
  'SELECT "already applied: payments.payment_mode" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.payment_sub_inst ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_sub_inst');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN payment_sub_inst VARCHAR(64) NULL AFTER payment_mode',
  'SELECT "already applied: payments.payment_sub_inst" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.card_network ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'card_network');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN card_network VARCHAR(12) NULL AFTER payment_sub_inst',
  'SELECT "already applied: payments.card_network" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.masked_card ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'masked_card');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN masked_card VARCHAR(32) NULL AFTER card_network',
  'SELECT "already applied: payments.masked_card" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.bank_resp_code ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'bank_resp_code');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN bank_resp_code VARCHAR(8) NULL AFTER masked_card',
  'SELECT "already applied: payments.bank_resp_code" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.bank_resp_desc ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'bank_resp_desc');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN bank_resp_desc VARCHAR(128) NULL AFTER bank_resp_code',
  'SELECT "already applied: payments.bank_resp_desc" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.bank_txn_time ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'bank_txn_time');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN bank_txn_time VARCHAR(14) NULL AFTER bank_resp_desc',
  'SELECT "already applied: payments.bank_txn_time" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.raw_callback ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'raw_callback');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN raw_callback MEDIUMTEXT NULL AFTER bank_txn_time',
  'SELECT "already applied: payments.raw_callback" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- payments.raw_status ----------
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'raw_status');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE payments ADD COLUMN raw_status MEDIUMTEXT NULL AFTER raw_callback',
  'SELECT "already applied: payments.raw_status" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
