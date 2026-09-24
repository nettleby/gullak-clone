-- ============================================================
--  MeraGullak migration: readable (plaintext) admin passwords
--  Run ONCE via phpMyAdmin Import with the `gullak` DB selected.
--  Re-runnable: safe to import twice (prints "already applied" notes).
--
--  OWNER DECISION (security downgrade, stated plainly): admin passwords
--  are stored readable so they can be seen in the database. Anyone who
--  can read this table owns the panel (credits, payouts, rates, users).
--
--  Bcrypt hashes cannot be reversed, so every existing admin password is
--  RESET to the documented seed `admin123` below — change it right after
--  importing (Admin → More → Change password). User passwords in the
--  `users` table stay bcrypt-hashed; this affects `admins` only.
-- ============================================================

USE gullak;

-- 1) add the readable column (guarded)
SET @has_col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'password_plain');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE admins ADD COLUMN password_plain VARCHAR(255) NOT NULL DEFAULT '''' AFTER username',
  'SELECT "already applied: admins.password_plain" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) reset admins to the known seed (only rows without a readable password yet)
UPDATE admins SET password_plain = 'admin123' WHERE password_plain IS NULL OR password_plain = '';

-- 3) drop the obsolete bcrypt column (guarded; code no longer reads it)
SET @has_hash := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'gullak' AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'password_hash');
SET @sql := IF(@has_hash = 1,
  'ALTER TABLE admins DROP COLUMN password_hash',
  'SELECT "already applied: password_hash dropped" AS note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
