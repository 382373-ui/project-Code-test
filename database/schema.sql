-- JobBridge Database Schema

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'employer', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_verified BOOLEAN DEFAULT FALSE,
    INDEX idx_username (username),
    INDEX idx_email (email)
);

CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    profile_img VARCHAR(255),
    grade_year VARCHAR(20),
    skills TEXT,
    availability VARCHAR(100),
    location_radius INT,
    bio TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poster_user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('company', 'odd', 'volunteer', 'internship') NOT NULL,
    pay DECIMAL(10, 2) NULL,
    zip_code VARCHAR(10),
    date_posted TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_needed DATE NULL,
    location_details VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    verified_flag BOOLEAN DEFAULT FALSE,
    attachments VARCHAR(255),
    FOREIGN KEY (poster_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_zip (zip_code),
    INDEX idx_category (category),
    INDEX idx_date_posted (date_posted)
);

CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    applicant_user_id INT NOT NULL,
    cover_text TEXT,
    resume_file_ref VARCHAR(255),
    status ENUM('applied', 'accepted', 'rejected') DEFAULT 'applied',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS saved_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (user_id, job_id)
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    job_id INT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS job_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    worker_user_id INT NOT NULL,
    poster_confirmed BOOLEAN DEFAULT FALSE,
    worker_confirmed BOOLEAN DEFAULT FALSE,
    proof_text TEXT,
    proof_file_ref VARCHAR(255),
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    reviewed_user_id INT NOT NULL,
    job_id INT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('job', 'user', 'message') NOT NULL,
    item_id INT NOT NULL,
    reported_by INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placement VARCHAR(50),
    html_content TEXT,
    start_date DATE,
    end_date DATE
);
