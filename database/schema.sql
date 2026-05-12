-- ============================================================
--  JobBridge — COMPLETE MySQL Schema  (Fresh Install)
--  Compatible: MySQL 5.7+ and MySQL 8.0+
--
--  HOW TO USE:
--    Option A (command line):
--      mysql -u root -p your_database_name < database/schema.sql
--
--    Option B (phpMyAdmin):
--      Open your database → click "SQL" tab → paste this file → click Go
--
--  This script drops and recreates every table.
--  Do NOT run on a database with data you want to keep — use migration.sql instead.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS monetization_log;
DROP TABLE IF EXISTS job_boosts;
DROP TABLE IF EXISTS job_ratings;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS experience;
DROP TABLE IF EXISTS flags;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS job_confirmations;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS saved_jobs;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS ads;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────────────────────
--  1. USERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE users (
    id            INT            NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)    NOT NULL,
    email         VARCHAR(100)   NOT NULL,
    password_hash VARCHAR(255)   NOT NULL,
    role          ENUM('student','employer','admin') NOT NULL DEFAULT 'student',
    is_verified   TINYINT(1)     NOT NULL DEFAULT 0,
    created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email    (email),
    KEY idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  2. PROFILES
--  UNIQUE KEY on user_id is required for ON DUPLICATE KEY UPDATE
--  used in edit-profile.php
-- ─────────────────────────────────────────────────────────────
CREATE TABLE profiles (
    id              INT           NOT NULL AUTO_INCREMENT,
    user_id         INT           NOT NULL,
    first_name      VARCHAR(60)   DEFAULT NULL,
    last_name       VARCHAR(60)   DEFAULT NULL,
    profile_img     VARCHAR(255)  DEFAULT NULL,
    grade_year      VARCHAR(30)   DEFAULT NULL,
    skills          TEXT          DEFAULT NULL,
    availability    VARCHAR(120)  DEFAULT NULL,
    location_radius INT           DEFAULT NULL,
    bio             TEXT          DEFAULT NULL,
    age             INT           DEFAULT NULL,
    is_public       TINYINT(1)    NOT NULL DEFAULT 1,
    tags            TEXT          DEFAULT NULL,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_profile_user (user_id),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  3. JOBS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE jobs (
    id               INT             NOT NULL AUTO_INCREMENT,
    poster_user_id   INT             NOT NULL,
    title            VARCHAR(200)    NOT NULL,
    description      TEXT            NOT NULL,
    category         ENUM('company','odd','volunteer','internship') NOT NULL,
    pay              DECIMAL(10,2)   DEFAULT NULL,
    pay_type         VARCHAR(20)     NOT NULL DEFAULT 'full',
    zip_code         VARCHAR(10)     DEFAULT NULL,
    location_details VARCHAR(255)    DEFAULT NULL,
    date_posted      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_needed      DATE            DEFAULT NULL,
    is_active        TINYINT(1)      NOT NULL DEFAULT 1,
    is_deleted       TINYINT(1)      NOT NULL DEFAULT 0,
    is_boosted       TINYINT(1)      NOT NULL DEFAULT 0,
    boost_amount     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    verified_flag    TINYINT(1)      NOT NULL DEFAULT 0,
    attachments      VARCHAR(255)    DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_category (category),
    KEY idx_zip      (zip_code),
    KEY idx_active   (is_active, is_deleted),
    KEY idx_boost    (is_boosted, boost_amount),
    KEY idx_posted   (date_posted),
    CONSTRAINT fk_jobs_poster FOREIGN KEY (poster_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  4. APPLICATIONS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE applications (
    id                INT          NOT NULL AUTO_INCREMENT,
    job_id            INT          NOT NULL,
    applicant_user_id INT          NOT NULL,
    cover_text        TEXT         DEFAULT NULL,
    resume_file_ref   VARCHAR(255) DEFAULT NULL,
    status            ENUM('applied','accepted','rejected') NOT NULL DEFAULT 'applied',
    applied_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_app_job  (job_id),
    KEY idx_app_user (applicant_user_id),
    CONSTRAINT fk_app_job  FOREIGN KEY (job_id)            REFERENCES jobs(id)  ON DELETE CASCADE,
    CONSTRAINT fk_app_user FOREIGN KEY (applicant_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  5. SAVED JOBS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE saved_jobs (
    id         INT       NOT NULL AUTO_INCREMENT,
    user_id    INT       NOT NULL,
    job_id     INT       NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_saved (user_id, job_id),
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_job  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  6. MESSAGES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE messages (
    id          INT        NOT NULL AUTO_INCREMENT,
    sender_id   INT        NOT NULL,
    receiver_id INT        NOT NULL,
    job_id      INT        DEFAULT NULL,
    content     TEXT       NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_msg_receiver (receiver_id),
    KEY idx_msg_sender   (sender_id),
    CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_job      FOREIGN KEY (job_id)      REFERENCES jobs(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  7. JOB CONFIRMATIONS  (dual-end confirmation flow)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE job_confirmations (
    id               INT          NOT NULL AUTO_INCREMENT,
    job_id           INT          NOT NULL,
    worker_user_id   INT          NOT NULL,
    proof_text       TEXT         DEFAULT NULL,
    proof_file_ref   VARCHAR(255) DEFAULT NULL,
    status           ENUM('pending','submitted','confirmed','disputed') NOT NULL DEFAULT 'pending',
    poster_confirmed TINYINT(1)   NOT NULL DEFAULT 0,
    worker_confirmed TINYINT(1)   NOT NULL DEFAULT 0,
    confirmed_at     TIMESTAMP    NULL     DEFAULT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_jc_job    (job_id),
    KEY idx_jc_worker (worker_user_id),
    CONSTRAINT fk_jc_job    FOREIGN KEY (job_id)         REFERENCES jobs(id)  ON DELETE CASCADE,
    CONSTRAINT fk_jc_worker FOREIGN KEY (worker_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  8. RATINGS  (user-to-user per job, shown on profile)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE ratings (
    id               INT       NOT NULL AUTO_INCREMENT,
    reviewer_id      INT       NOT NULL,
    reviewed_user_id INT       NOT NULL,
    job_id           INT       NOT NULL,
    rating           TINYINT   NOT NULL,
    comment          TEXT      DEFAULT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_rat_reviewer  FOREIGN KEY (reviewer_id)      REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rat_reviewed  FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rat_job       FOREIGN KEY (job_id)           REFERENCES jobs(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  9. JOB RATINGS  (per-listing star rating with comment,
--                   shown inline on jobs.php)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE job_ratings (
    id         INT       NOT NULL AUTO_INCREMENT,
    job_id     INT       NOT NULL,
    user_id    INT       NOT NULL,
    rating     TINYINT   NOT NULL,
    comment    TEXT      DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_job_user_rating (job_id, user_id),
    CONSTRAINT chk_jr_range CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_jr_job  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE CASCADE,
    CONSTRAINT fk_jr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  10. FLAGS  (reports / disputes)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE flags (
    id          INT       NOT NULL AUTO_INCREMENT,
    item_type   ENUM('job','user','message') NOT NULL,
    item_id     INT       NOT NULL,
    reported_by INT       NOT NULL,
    reason      TEXT      DEFAULT NULL,
    status      ENUM('pending','reviewed','resolved') NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_flag_status (status),
    CONSTRAINT fk_flag_reporter FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  11. ADS  (ad slot management)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE ads (
    id           INT         NOT NULL AUTO_INCREMENT,
    placement    VARCHAR(50) DEFAULT NULL,
    html_content TEXT        DEFAULT NULL,
    start_date   DATE        DEFAULT NULL,
    end_date     DATE        DEFAULT NULL,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
--  12. JOB BOOSTS  (paid job promotion)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE job_boosts (
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
--  13. MONETIZATION LOG  (revenue tracking for admin dashboard)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE monetization_log (
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
--  14. EXPERIENCE  (resume builder)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE experience (
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
--  15. PROJECTS  (resume builder)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE projects (
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
--  SAMPLE ADMIN ACCOUNT
--  Username: admin
--  Password: Admin@1234
--  *** Change this password immediately after setup! ***
--
--  To generate your own hash:
--    php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"
-- ─────────────────────────────────────────────────────────────
INSERT INTO users (username, email, password_hash, role, is_verified) VALUES (
    'admin',
    'admin@jobbridge.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1
);
