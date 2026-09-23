-- ============================================================
--  MeraGullak — Gullak-style Digital Gold & Silver platform
--  Database schema for MySQL / MariaDB (utf8mb4, InnoDB)
--
--  Import via phpMyAdmin  OR  command line:
--      mysql -u root < schema.sql
--
--  Default admin login:  admin / admin123   (change immediately!)
-- ============================================================

CREATE DATABASE IF NOT EXISTS gullak CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gullak;

-- ---------- users ----------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)  NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  phone         VARCHAR(15)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  notify_email  TINYINT(1)   NOT NULL DEFAULT 1,   -- transactional email updates
  notify_sms    TINYINT(1)   NOT NULL DEFAULT 0,   -- SMS updates (off by default)
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- wallets (one per user, INR balance) ----------
CREATE TABLE IF NOT EXISTS wallets (
  user_id    INT UNSIGNED PRIMARY KEY,
  balance    DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- metal holdings (grams held + amount invested) ----------
CREATE TABLE IF NOT EXISTS holdings (
  user_id  INT UNSIGNED NOT NULL,
  metal    ENUM('gold','silver') NOT NULL,
  grams    DECIMAL(14,4) NOT NULL DEFAULT 0,
  invested DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, metal),
  CONSTRAINT fk_holdings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- metal prices (admin-managed current rates, ₹/gram) ----------
--  buy_rate  : what a USER PAYS when buying   (higher)
--  sell_rate : what a USER GETS when selling  (lower)
CREATE TABLE IF NOT EXISTS metal_prices (
  metal      ENUM('gold','silver') PRIMARY KEY,
  buy_rate   DECIMAL(12,2) NOT NULL,
  sell_rate  DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by VARCHAR(50)   NULL,
  updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- price history (a row is added every time admin changes rates) ----------
CREATE TABLE IF NOT EXISTS price_history (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  metal       ENUM('gold','silver') NOT NULL,
  buy_rate    DECIMAL(12,2) NOT NULL,
  sell_rate   DECIMAL(12,2) NOT NULL,
  recorded_by VARCHAR(50)   NULL,
  recorded_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ph_metal_time (metal, recorded_at)
) ENGINE=InnoDB;

-- ---------- unified ledger ----------
--  One table for every money/metal movement. Signed deltas + "after"
--  snapshots make history & statements trivial to render.
--  type: deposit | buy | sell | sip_buy | withdraw_request |
--        withdraw_refund | withdraw_paid | admin_credit | admin_debit
CREATE TABLE IF NOT EXISTS transactions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  type         VARCHAR(24)  NOT NULL,
  amount       DECIMAL(14,2) NOT NULL,             -- absolute rupee value
  wallet_delta DECIMAL(14,2) NOT NULL DEFAULT 0,  -- signed wallet change
  wallet_after DECIMAL(14,2) NOT NULL DEFAULT 0,
  metal        ENUM('gold','silver') NULL,
  grams_delta  DECIMAL(14,4) NULL,                -- signed grams change
  grams_after  DECIMAL(14,4) NULL,
  rate         DECIMAL(12,2) NULL,                 -- ₹/gram rate applied
  note         VARCHAR(255) NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_txn_user_time (user_id, created_at),
  CONSTRAINT fk_txn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- SIP (auto-invest) plans ----------
CREATE TABLE IF NOT EXISTS sip_plans (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  metal           ENUM('gold','silver') NOT NULL,
  amount_inr      DECIMAL(12,2) NOT NULL,
  frequency       ENUM('daily','weekly','monthly') NOT NULL,
  next_run        DATE NOT NULL,
  status          ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sip_due (status, next_run),
  CONSTRAINT fk_sip_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- SIP execution log ----------
CREATE TABLE IF NOT EXISTS sip_logs (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sip_id     INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  run_date   DATE NOT NULL,
  status     ENUM('success','failed') NOT NULL,
  amount     DECIMAL(12,2) NULL,
  grams      DECIMAL(14,4) NULL,
  rate       DECIMAL(12,2) NULL,
  reason     VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_siplog_sip (sip_id, run_date),
  CONSTRAINT fk_siplog_sip FOREIGN KEY (sip_id) REFERENCES sip_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- saved bank accounts (for withdrawals) ----------
CREATE TABLE IF NOT EXISTS bank_accounts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  holder_name    VARCHAR(80) NOT NULL,
  account_number VARCHAR(20) NOT NULL,
  ifsc           VARCHAR(11) NOT NULL,
  bank_name      VARCHAR(80) NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bank_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- withdrawal requests (admin approves / rejects) ----------
CREATE TABLE IF NOT EXISTS withdrawals (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  bank_account_id INT UNSIGNED NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note      VARCHAR(255) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  processed_at    TIMESTAMP NULL,
  processed_by    VARCHAR(50) NULL,
  INDEX idx_wd_status (status, created_at),
  CONSTRAINT fk_wd_user  FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wd_bank  FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id)
) ENGINE=InnoDB;

-- ---------- ICICI Bank PG payment orders ----------
CREATE TABLE IF NOT EXISTS payments (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  order_id   VARCHAR(40)  NOT NULL UNIQUE,
  amount     DECIMAL(12,2) NOT NULL,
  status     ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
  payment_id VARCHAR(40)  NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  paid_at    TIMESTAMP NULL,
  INDEX idx_pay_user (user_id, created_at),
  CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- admins (separate from users) ----------
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- platform settings (admin-editable key-value store) ----------
CREATE TABLE IF NOT EXISTS settings (
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
  setting_value VARCHAR(64)  NOT NULL,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
--  Seed data
-- ============================================================

-- Default admin: admin / admin123   (bcrypt hash) — CHANGE IT after login!
INSERT INTO admins (username, password_hash)
VALUES ('admin', '$2y$12$UKqyHF6q/Pq2X9HRv3/6F.HGErMXb2zrSQlNlhV4kHTO09QC13evq');

-- Initial metal rates (₹ per gram). Adjust from the admin panel anytime.
INSERT INTO metal_prices (metal, buy_rate, sell_rate, updated_by) VALUES
  ('gold',   11250.00, 10980.00, 'system'),
  ('silver',   138.00,   128.00, 'system');

INSERT INTO price_history (metal, buy_rate, sell_rate, recorded_by) VALUES
  ('gold',   11250.00, 10980.00, 'system'),
  ('silver',   138.00,   128.00, 'system');

-- Default platform spreads (% over/under market mid, editable in admin panel)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('gold_buy_pct',   '1.5'),
  ('gold_sell_pct',  '1.5'),
  ('silver_buy_pct', '4.0'),
  ('silver_sell_pct','4.0');
