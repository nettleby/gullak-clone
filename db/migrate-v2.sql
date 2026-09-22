-- ============================================================
--  MeraGullak — migration v1 → v2
--  Run this ONCE if you imported the ORIGINAL schema.sql
--  (before the Settings page existed). If you are importing
--  db/schema.sql fresh, you do NOT need this file.
--
--  Import via phpMyAdmin  OR:
--      mysql -u root gullak < migrate-v2.sql
-- ============================================================

USE gullak;

-- notification preferences (Settings page)
ALTER TABLE users
  ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active,
  ADD COLUMN notify_sms   TINYINT(1) NOT NULL DEFAULT 0 AFTER notify_email;
