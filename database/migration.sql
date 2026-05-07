-- JobBridge Migration: All new tables & columns

-- 1. Add missing columns to jobs table
ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS is_boosted TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS boost_amount DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS pay_type VARCHAR(20) DEFAULT 'full';

-- 2. Add is_public and tags to profiles
ALTER TABLE profiles
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS tags TEXT NULL,
    ADD COLUMN IF NOT EXISTS age INT NULL,
    ADD UNIQUE INDEX IF NOT EXISTS idx_profile_user (user_id);

-- 3. Add status and ensure other columns on job_confirmations
ALTER TABLE job_confirmations
    ADD COLUMN IF NOT EXISTS proof_file_ref VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS poster_confirmed TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status ENUM('pending','submitted','confirmed','disputed') DEFAULT 'pending';

-- 4. Job ratings table (with comment)
CREATE TABLE IF NOT EXISTS job_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_job_user_rating (job_id, user_id)
);

-- 5. Job boosts table
CREATE TABLE IF NOT EXISTS job_boosts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    duration VARCHAR(20) NOT NULL,
    expiry_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Monetization log
CREATE TABLE IF NOT EXISTS monetization_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('boost','ad_impression','subscription','other') NOT NULL DEFAULT 'boost',
    amount DECIMAL(10,2) DEFAULT 0.00,
    reference_id INT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. Experience entries for resumes
CREATE TABLE IF NOT EXISTS experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company VARCHAR(150) NOT NULL,
    position VARCHAR(150) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_current TINYINT(1) DEFAULT 0,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. Project entries for resumes
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
