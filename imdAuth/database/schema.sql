-- ==========================================
-- IMD RESEARCH & GUIDE PORTAL - DATABASE SCHEMA (DDL)
-- ==========================================
-- Dialect: MySQL / MariaDB (Standard SQL Compatible)

CREATE DATABASE IF NOT EXISTS ftc_grading DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ftc_grading;

-- 1. DISABLE FOREIGN KEY CHECKS FOR SAFE INITIALIZATION
SET FOREIGN_KEY_CHECKS = 0;

-- Drop Stored Procedures (To safely allow re-creation/updates without dropping user data)
DROP PROCEDURE IF EXISTS sp_register_user;
DROP PROCEDURE IF EXISTS sp_approve_user_registration;
DROP PROCEDURE IF EXISTS sp_reject_user_registration;
DROP PROCEDURE IF EXISTS sp_update_user_profile;
DROP PROCEDURE IF EXISTS sp_create_dept_admin;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 2. CREATE CORE ENTITY TABLES (PRESERVE EXISTING USER DATA)
-- ==========================================

-- A. ROLES TABLE
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Roles (1: guide, 2: dept_admin, 3: super_admin)
INSERT IGNORE INTO roles (role_id, role_name, description) VALUES
(1, 'guide', 'IMD Scientist / Faculty Supervisor'),
(2, 'dept_admin', 'Departmental Administrator'),
(3, 'super_admin', 'Super Administrator / System Director');

-- B. USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    account_status ENUM('pending_approval', 'active', 'rejected', 'suspended') NOT NULL DEFAULT 'pending_approval',
    rejection_reason TEXT NULL,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles (role_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- C. PROFILES TABLE
CREATE TABLE IF NOT EXISTS profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    dob DATE NULL,
    department VARCHAR(100) NULL,
    photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_users FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- D. OTP VERIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS otp_verifications (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    otp_last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_otp_users FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E. AUDIT LOGS TABLE
CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_users FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- 3. CREATE INDEXES FOR OPTIMIZATION
-- ==========================================
-- Ignore errors if indexes already exist
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_name = 'users' AND table_schema = DATABASE() AND index_name = 'idx_users_email');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_users_email ON users(email)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================
-- 4. SEED INITIAL SUPER ADMIN USER (SAFE INSERT)
-- ==========================================
-- Default Super Admin (email: aryaaa.pawar@gmail.com, pass: 12345678)
INSERT IGNORE INTO users (user_id, email, password_hash, role_id, account_status, created_at) VALUES
(1, 'aryaaa.pawar@gmail.com', '$2y$10$0YrhcvTP6bcXMymn49UqvOCI.dI/XRNRUIAzOrGu.NlIaGwCs.NM6', 3, 'active', NOW());

INSERT IGNORE INTO profiles (user_id, full_name, phone, department) VALUES
(1, 'Director IMD', '011-24612345', 'Executive Division');

-- ==========================================
-- 5. STORED PROCEDURES
-- ==========================================

DELIMITER //

-- Procedure 1: Register User (Atomic insertion into users, profiles, and audit_logs)
CREATE PROCEDURE sp_register_user(
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_role_name VARCHAR(50),
    IN p_full_name VARCHAR(150),
    IN p_phone VARCHAR(20),
    IN p_department VARCHAR(100),
    IN p_ip_address VARCHAR(45),
    OUT p_new_user_id INT
)
BEGIN
    DECLARE v_role_id INT;
    
    -- Resolve Role ID
    SELECT role_id INTO v_role_id FROM roles WHERE role_name = p_role_name LIMIT 1;
    IF v_role_id IS NULL THEN
        SET v_role_id = 1; -- Default to 'guide' if role not found
    END IF;

    -- Insert User
    INSERT INTO users (email, password_hash, role_id, account_status, created_at)
    VALUES (p_email, p_password_hash, v_role_id, 'pending_approval', NOW());
    
    SET p_new_user_id = LAST_INSERT_ID();

    -- Insert Profile
    INSERT INTO profiles (user_id, full_name, phone, department)
    VALUES (p_new_user_id, p_full_name, p_phone, p_department);

    -- Log Audit Event
    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_new_user_id, 'REGISTRATION_SUBMITTED', p_ip_address, NOW());
END //

-- Procedure 2: Approve User Registration
CREATE PROCEDURE sp_approve_user_registration(
    IN p_target_user_id INT,
    IN p_admin_user_id INT,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    UPDATE users 
    SET account_status = 'active', rejection_reason = NULL 
    WHERE user_id = p_target_user_id;

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_admin_user_id, CONCAT('ACCOUNT_APPROVED (User ID: ', p_target_user_id, ')'), p_ip_address, NOW());
END //

-- Procedure 3: Reject User Registration
CREATE PROCEDURE sp_reject_user_registration(
    IN p_target_user_id INT,
    IN p_rejection_reason TEXT,
    IN p_admin_user_id INT,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    UPDATE users 
    SET account_status = 'rejected', rejection_reason = p_rejection_reason 
    WHERE user_id = p_target_user_id;

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_admin_user_id, CONCAT('ACCOUNT_REJECTED (User ID: ', p_target_user_id, ')'), p_ip_address, NOW());
END //

-- Procedure 4: Update User Profile
CREATE PROCEDURE sp_update_user_profile(
    IN p_user_id INT,
    IN p_full_name VARCHAR(150),
    IN p_phone VARCHAR(20),
    IN p_address TEXT,
    IN p_dob DATE,
    IN p_department VARCHAR(100),
    IN p_photo_path VARCHAR(255),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    IF p_photo_path IS NOT NULL AND p_photo_path != '' THEN
        UPDATE profiles 
        SET full_name = p_full_name,
            phone = p_phone,
            address = p_address,
            dob = p_dob,
            department = p_department,
            photo_path = p_photo_path
        WHERE user_id = p_user_id;
    ELSE
        UPDATE profiles 
        SET full_name = p_full_name,
            phone = p_phone,
            address = p_address,
            dob = p_dob,
            department = p_department
        WHERE user_id = p_user_id;
    END IF;

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_user_id, 'PROFILE_UPDATED', p_ip_address, NOW());
END //

-- Procedure 5: Create Department Admin Account
CREATE PROCEDURE sp_create_dept_admin(
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_full_name VARCHAR(150),
    IN p_admin_user_id INT,
    IN p_ip_address VARCHAR(45),
    OUT p_new_user_id INT
)
BEGIN
    DECLARE v_dept_admin_role_id INT;

    SELECT role_id INTO v_dept_admin_role_id FROM roles WHERE role_name = 'dept_admin' LIMIT 1;
    IF v_dept_admin_role_id IS NULL THEN
        SET v_dept_admin_role_id = 2;
    END IF;

    INSERT INTO users (email, password_hash, role_id, account_status, created_at)
    VALUES (p_email, p_password_hash, v_dept_admin_role_id, 'active', NOW());

    SET p_new_user_id = LAST_INSERT_ID();

    INSERT INTO profiles (user_id, full_name, department)
    VALUES (p_new_user_id, p_full_name, 'Department Admin');

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_admin_user_id, CONCAT('DEPT_ADMIN_CREATED (New User ID: ', p_new_user_id, ')'), p_ip_address, NOW());
END //

DELIMITER ;

USE ftc_grading;
SELECT * FROM users;
SELECT * FROM profiles;
