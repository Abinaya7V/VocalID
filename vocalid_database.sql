-- ============================================================
-- VocalID — Voice Biometric Attendance System
-- Database Setup Script
-- Run this in phpMyAdmin or MySQL CLI before using the app
-- ============================================================

-- Step 1: Create the database
CREATE DATABASE IF NOT EXISTS vocalid CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Step 2: Select the database
USE vocalid;

-- Step 3: Create the users table
-- Stores faculty accounts (login credentials)
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100) NOT NULL UNIQUE,         -- Faculty ID, e.g. FAC-2024-001
    password    VARCHAR(255) NOT NULL,                 -- bcrypt hashed password
    role        ENUM('faculty', 'admin') DEFAULT 'faculty',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Step 4: Create the students table
-- Stores enrolled students with their class info
CREATE TABLE IF NOT EXISTS students (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    roll_no     VARCHAR(50)  NOT NULL UNIQUE,
    department  VARCHAR(10)  NOT NULL,
    class_id    VARCHAR(20)  DEFAULT NULL,            -- e.g. class1, class2
    enrolled    TINYINT(1)   DEFAULT 0,               -- 1 = voice profile created
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Step 5: Create the attendance table
-- Every marked attendance stores student name, date, time
CREATE TABLE IF NOT EXISTS attendance (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_name    VARCHAR(150) NOT NULL,
    roll_no         VARCHAR(50)  NOT NULL,
    class_id        VARCHAR(20)  NOT NULL,
    date            DATE         NOT NULL,
    time            TIME         NOT NULL,
    status          ENUM('present','absent') DEFAULT 'present',
    confidence      INT          DEFAULT 0,           -- voice match confidence %
    marked_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Step 6: Insert demo faculty accounts
-- Passwords are bcrypt hashes — see instructions below
-- Default password for all demo accounts: "faculty123"
-- ============================================================

INSERT INTO users (username, password, role) VALUES
('FAC-2024-001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty'),
('FAC-2024-002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty'),
('admin',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- NOTE: The hash above is the bcrypt hash of "password" (Laravel default demo hash).
-- To generate your own hash, run this PHP snippet:
--   echo password_hash('your_password', PASSWORD_BCRYPT);
-- Then replace the hash values above.

-- ============================================================
-- Step 7: Insert demo students (matching vocalid.js hardcoded data)
-- ============================================================

INSERT INTO students (name, email, roll_no, department, class_id, enrolled) VALUES
('Abinaya Velliyangiri', 'abinaya@college.edu', 'CS001', 'CS', 'class1', 1),
('Arjun Vijayakumar',    'arjun@college.edu',   'CS002', 'CS', 'class1', 1),
('Janasruthi Gopi',      'janasruthi@college.edu','CS003','CS', 'class1', 1),
('Mohamed Aashiq',       'aashiq@college.edu',   'CS004', 'CS', 'class1', 1),
('Deepika Rajamanickam', 'deepika@college.edu',  'CS005', 'CS', 'class1', 1),
('Sreeharan Senthilkumar','sreeharan@college.edu','CS006','CS', 'class1', 1),
('Naveena Padmanathan',  'naveena@college.edu',  'CS007', 'CS', 'class1', 1),
('Keerthan KS',          'keerthan@college.edu', 'CS008', 'CS', 'class1', 1),
('Monisha Saravanan',    'monisha@college.edu',  'IT101', 'IT', 'class2', 1),
('Nivedha Baskar',       'nivedha@college.edu',  'IT102', 'IT', 'class2', 1),
('Vishnu Sanjay',        'vishnu@college.edu',   'IT103', 'IT', 'class2', 1),
('Santhosh Saravanan',   'santhosh@college.edu', 'IT104', 'IT', 'class2', 1),
('Varshan Gopi',         'varshan@college.edu',  'IT105', 'IT', 'class2', 1);

-- ============================================================
-- Useful queries for reference:
-- ============================================================

-- View all attendance records:
-- SELECT * FROM attendance ORDER BY date DESC, time DESC;

-- Count present students per class today:
-- SELECT class_id, COUNT(*) as present_count FROM attendance
--   WHERE date = CURDATE() AND status = 'present' GROUP BY class_id;

-- Get student attendance percentage:
-- SELECT student_name, COUNT(*) as total_days,
--   SUM(status='present') as present_days,
--   ROUND(SUM(status='present') / COUNT(*) * 100, 1) as attendance_pct
-- FROM attendance GROUP BY student_name ORDER BY attendance_pct DESC;
