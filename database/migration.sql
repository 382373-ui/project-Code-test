-- ============================================================
--  JobBridge — MySQL Migration Script  (Existing Database)
--  Run this if you ALREADY have the old schema and want to
--  add all the new columns and tables without losing data.
--
--  Compatible: MySQL 5.7+ and MySQL 8.0+
--
--  HOW TO USE:
--    Option A (command line):
--      mysql -u root -p your_database_name < database/migration.sql
--
--    Option B (phpMyAdmin):
--      Open your database → SQL tab → paste this file → click Go
--
--  NOTE: "ADD COLUMN IF NOT EXISTS" only works in MySQL 8.0.3+.
--  This script uses stored procedures so it works on MySQL 5.7 too.
-- ============================================================

DELIMITER $$

-- Helper: add a column only if it does not already exist
DROP PROCEDURE IF EXISTS add_col $$
CREATE PROCEDURE add_col(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_def    TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = p_table
          AND  COLUMN_NAME  = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table,
                          '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE s FROM @ddl;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END $$

-- Helper: add a unique index only if it does not already exist
DROP PROCEDURE IF EXISTS add_uq $$
CREATE PROCEDURE add_uq(
    IN p_table  VARCHAR(64),
    IN p_index  VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.STATISTICS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = p_table
          AND  INDEX_NAME   = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table,
                          '` ADD UNIQUE KEY `', p_index,
                          '` (`', p_column, '`)');
        PREPARE s FROM @ddl;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END $$

DELIMITER ;

-- ─────────────────────────────────────────────────────────────
--  STEP 1 — New columns on JOBS
-- ─────────────────────────────────────────────────────────────
CALL add_col('jobs', 'pay_type',     "VARCHAR(20) NOT NULL DEFAULT 'full'");
CALL add_col('jobs', 'is_deleted',   'TINYINT(1)  NOT NULL DEFAULT 0');
CALL add_col('jobs', 'is_boosted',   'TINYINT(1)  NOT NULL DEFAULT 0');
CALL add_col('jobs', 'boost_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');

-- ─────────────────────────────────────────────────────────────
--  STEP 2 — New columns on PROFILES + UNIQUE index for upsert
-- ─────────────────────────────────────────────────────────────
CALL add_col('profiles', 'age',       'INT DEFAULT NULL');
CALL add_col('profiles', 'is_public', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL add_col('profiles', 'tags',      'TEXT DEFAULT NULL');

-- UNIQUE KEY on user_id is required for ON DUPLICATE KEY UPDATE in edit-profile.php
CALL add_uq ('profiles', 'uq_profile_user', 'user_id');

-- ─────────────────────────────────────────────────────────────
--  STEP 3 — New columns on JOB_CONFIRMATIONS
-- ─────────────────────────────────────────────────────────────
CALL add_col('job_confirmations', 'proof_file_ref',
             'VARCHAR(255) DEFAULT NULL');
CALL add_col('job_confirmations', 'poster_confirmed',
             'TINYINT(1) NOT NULL DEFAULT 0');
CALL add_col('job_confirmations', 'status',
             "ENUM('pending','submitted','confirmed','disputed') NOT NULL DEFAULT 'pending'");
CALL add_col('job_confirmations', 'created_at',
             'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

-- ─────────────────────────────────────────────────────────────
--  STEP 4 — Add comment column to RATINGS (if missing)
-- ─────────────────────────────────────────────────────────────
CALL add_col('ratings', 'comment', 'TEXT DEFAULT NULL');

-- ─────────────────────────────────────────────────────────────
--  STEP 5 — New table: JOB_RATINGS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS job_ratings (
    id         INT       NOT NULL AUTO_INCREMENT,
    job_id     INT       NOT NULL,
    user_id    INT       NOT NULL,
    rating     TINYINT   NOT NULL,
    comment    TEXT      DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_job_user_rating (job_id, user_id),
    CONSTRAINT fk_jr_job  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE CASCADE,
    CONSTRAINT fk_jr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  STEP 6 — New table: JOB_BOOSTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS job_boosts (
    id          INT           NOT NULL AUTO_INCREMENT,
    job_id      INT           NOT NULL,
    user_id     INT           NOT NULL,
    amount      DECIMAL(10,2) NOT NULL,
    duration    VARCHAR(20)   NOT NULL,
    expiry_date DATETIME      NOT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_boost_expiry (expiry_date),
    CONSTRAINT fk_boost_job  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE CASCADE,
    CONSTRAINT fk_boost_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  STEP 7 — New table: MONETIZATION_LOG
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS monetization_log (
    id           INT           NOT NULL AUTO_INCREMENT,
    user_id      INT           NOT NULL,
    type         ENUM('boost','ad_impression','subscription','other') NOT NULL DEFAULT 'boost',
    amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reference_id INT           DEFAULT NULL,
    description  VARCHAR(255)  DEFAULT NULL,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_ml_type (type),
    CONSTRAINT fk_ml_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  STEP 8 — New table: EXPERIENCE
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS experience (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    company     VARCHAR(150) NOT NULL,
    position    VARCHAR(150) NOT NULL,
    start_date  DATE         DEFAULT NULL,
    end_date    DATE         DEFAULT NULL,
    is_current  TINYINT(1)   NOT NULL DEFAULT 0,
    description TEXT         DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_exp_user (user_id),
    CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  STEP 9 — New table: PROJECTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT         DEFAULT NULL,
    url         VARCHAR(500) DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_proj_user (user_id),
    CONSTRAINT fk_proj_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  CLEANUP helper procedures
-- ─────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS add_col;
DROP PROCEDURE IF EXISTS add_uq;

-- ─────────────────────────────────────────────────────────────
--  DONE — All new columns and tables added. Existing data preserved.
-- ─────────────────────────────────────────────────────────────
