-- FTC Grading authentication integration.
-- Run once against the existing ftc_grading database after taking a backup.
USE ftc_grading;

SET @has_batch_lock := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'batches'
      AND column_name = 'is_locked'
);
SET @sql := IF(
    @has_batch_lock = 0,
    'ALTER TABLE batches ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER station',
    'SELECT 1'
);
PREPARE batch_lock_stmt FROM @sql;
EXECUTE batch_lock_stmt;
DEALLOCATE PREPARE batch_lock_stmt;

CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    account_status ENUM('pending_approval','active','rejected','suspended') NOT NULL DEFAULT 'pending_approval',
    rejection_reason TEXT NULL,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ftc_users_roles FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    department VARCHAR(100) NULL,
    CONSTRAINT fk_ftc_profiles_users FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @profile_address := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'profiles' AND column_name = 'address');
SET @sql := IF(@profile_address = 0, 'ALTER TABLE profiles ADD COLUMN address TEXT NULL AFTER phone', 'SELECT 1');
PREPARE profile_stmt FROM @sql;
EXECUTE profile_stmt;
DEALLOCATE PREPARE profile_stmt;

SET @profile_dob := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'profiles' AND column_name = 'dob');
SET @sql := IF(@profile_dob = 0, 'ALTER TABLE profiles ADD COLUMN dob DATE NULL AFTER address', 'SELECT 1');
PREPARE profile_stmt FROM @sql;
EXECUTE profile_stmt;
DEALLOCATE PREPARE profile_stmt;

SET @profile_photo := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'profiles' AND column_name = 'photo_path');
SET @sql := IF(@profile_photo = 0, 'ALTER TABLE profiles ADD COLUMN photo_path VARCHAR(255) NULL AFTER dob', 'SELECT 1');
PREPARE profile_stmt FROM @sql;
EXECUTE profile_stmt;
DEALLOCATE PREPARE profile_stmt;

CREATE TABLE IF NOT EXISTS otp_verifications (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    otp_last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ftc_otp_users FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_ftc_otp_active (user_id, is_used, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ftc_audit_users FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (role_name, description) VALUES
('guide', 'Faculty supervisor and marks entry user'),
('dept_admin', 'Department administrator'),
('super_admin', 'System administrator'),
('admin', 'Legacy FTC administrator'),
('verification', 'Marks verification officer');

INSERT INTO users (email, password_hash, role_id, account_status)
SELECT 'aryaaa.pawar@gmail.com', '$2y$10$f9ap.uf93eDwTjuItOu9k.AoxkjAyoR5334XHXpuD/CPb.rPbsxzq', r.role_id, 'active'
FROM roles r
WHERE r.role_name = 'super_admin'
    AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'aryaaa.pawar@gmail.com');

INSERT IGNORE INTO profiles (user_id, full_name, department)
SELECT u.user_id, 'Director IMD', 'Executive Division'
FROM users u
WHERE u.email = 'aryaaa.pawar@gmail.com';

INSERT IGNORE INTO permissions (permission_name, description) VALUES
('marks.enter', 'Enter and submit faculty marks'),
('marks.view', 'View marks and audit data'),
('marks.edit', 'Edit or override marks'),
('results.view', 'View and generate results'),
('results.download', 'Download result documents'),
('admin.access', 'Access administrative pages'),
('verification.access', 'Access verification pages'),
('users.manage', 'Approve and manage user accounts');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name IN ('dept_admin','super_admin','admin');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name = 'guide' AND p.permission_name IN ('marks.enter','marks.view','results.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name = 'verification' AND p.permission_name IN ('marks.view','verification.access');

SET @has_password := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'faculties' AND column_name = 'password');
SET @sql := IF(@has_password = 0, 'ALTER TABLE faculties ADD COLUMN password VARCHAR(255) NULL', 'SELECT 1');
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @has_user_id := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'faculties' AND column_name = 'user_id');
SET @sql := IF(@has_user_id = 0, 'ALTER TABLE faculties ADD COLUMN user_id INT NULL UNIQUE', 'SELECT 1');
PREPARE migration_stmt FROM @sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

INSERT INTO users (email, password_hash, role_id, account_status)
SELECT f.email, COALESCE(NULLIF(f.password, ''), '$2y$10$VTZekWvf984LW4tkKiD3ae1Xx0rDUZ21lNAJJQ9WALllfQs9W0vpG'),
       (SELECT role_id FROM roles WHERE role_name = 'guide' LIMIT 1), 'active'
FROM faculties f LEFT JOIN users u ON u.email = f.email
WHERE f.email IS NOT NULL AND f.email <> '' AND u.user_id IS NULL
  AND (f.is_deleted = 0 OR f.is_deleted IS NULL);

INSERT IGNORE INTO profiles (user_id, full_name, phone)
SELECT u.user_id, f.faculty_name, f.contact_number
FROM faculties f JOIN users u ON u.email = f.email
LEFT JOIN profiles p ON p.user_id = u.user_id
WHERE p.user_id IS NULL;

UPDATE faculties f JOIN users u ON u.email = f.email SET f.user_id = u.user_id
WHERE f.user_id IS NULL;

-- Existing legacy passwords are retained above; replace any placeholder before use.

SET @has_scrutiny_remarks := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'students'
      AND column_name = 'scrutiny_remarks'
);
SET @sql := IF(
    @has_scrutiny_remarks = 0,
    'ALTER TABLE students ADD COLUMN scrutiny_remarks VARCHAR(500) NULL AFTER custom_remarks',
    'SELECT 1'
);
PREPARE scrutiny_remarks_stmt FROM @sql;
EXECUTE scrutiny_remarks_stmt;
DEALLOCATE PREPARE scrutiny_remarks_stmt;

SET @has_verification_status := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'students'
      AND column_name = 'verification_status'
);
SET @sql := IF(
    @has_verification_status = 0,
    "ALTER TABLE students ADD COLUMN verification_status ENUM('correct','wrong') NULL AFTER scrutiny_remarks",
    'SELECT 1'
);
PREPARE verification_status_stmt FROM @sql;
EXECUTE verification_status_stmt;
DEALLOCATE PREPARE verification_status_stmt;

SET @has_verification_notified_at := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'students'
      AND column_name = 'verification_notified_at'
);
SET @sql := IF(
    @has_verification_notified_at = 0,
    'ALTER TABLE students ADD COLUMN verification_notified_at DATETIME NULL AFTER verification_status',
    'SELECT 1'
);
PREPARE verification_notified_at_stmt FROM @sql;
EXECUTE verification_notified_at_stmt;
DEALLOCATE PREPARE verification_notified_at_stmt;

CREATE TABLE IF NOT EXISTS admin_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    batch_id INT NOT NULL,
    entry_id INT NULL,
    verification_status ENUM('correct','wrong') NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_notifications_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_notifications_batch FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE,
    INDEX idx_admin_notifications_read (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_admin_notification_entry := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'admin_notifications' AND column_name = 'entry_id'
);
SET @sql := IF(@has_admin_notification_entry = 0, 'ALTER TABLE admin_notifications ADD COLUMN entry_id INT NULL AFTER batch_id', 'SELECT 1');
PREPARE admin_notification_entry_stmt FROM @sql;
EXECUTE admin_notification_entry_stmt;
DEALLOCATE PREPARE admin_notification_entry_stmt;

SET @has_mark_verification_status := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'faculty_marks_entry' AND column_name = 'verification_status'
);
SET @sql := IF(@has_mark_verification_status = 0, "ALTER TABLE faculty_marks_entry ADD COLUMN verification_status ENUM('correct','wrong') NULL", 'SELECT 1');
PREPARE mark_verification_status_stmt FROM @sql;
EXECUTE mark_verification_status_stmt;
DEALLOCATE PREPARE mark_verification_status_stmt;

SET @has_mark_verification_notified := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'faculty_marks_entry' AND column_name = 'verification_notified_at'
);
SET @sql := IF(@has_mark_verification_notified = 0, 'ALTER TABLE faculty_marks_entry ADD COLUMN verification_notified_at DATETIME NULL', 'SELECT 1');
PREPARE mark_verification_notified_stmt FROM @sql;
EXECUTE mark_verification_notified_stmt;
DEALLOCATE PREPARE mark_verification_notified_stmt;

CREATE TABLE IF NOT EXISTS correction_notifications (
    correction_notification_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    entry_id INT NOT NULL,
    original_mark DECIMAL(5,2) NULL,
    corrected_mark DECIMAL(5,2) NULL,
    corrected_by_faculty_id INT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    UNIQUE KEY uq_correction_notification (notification_id),
    UNIQUE KEY uq_correction_entry (entry_id),
    CONSTRAINT fk_correction_notification_admin FOREIGN KEY (notification_id) REFERENCES admin_notifications(notification_id) ON DELETE CASCADE,
    CONSTRAINT fk_correction_notification_entry FOREIGN KEY (entry_id) REFERENCES faculty_marks_entry(entry_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_batch_number := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'batches'
      AND column_name = 'batch_number'
);
SET @sql := IF(
    @has_batch_number = 0,
    'ALTER TABLE batches ADD COLUMN batch_number VARCHAR(50) NULL AFTER batch_id',
    'SELECT 1'
);
PREPARE batch_number_stmt FROM @sql;
EXECUTE batch_number_stmt;
DEALLOCATE PREPARE batch_number_stmt;

UPDATE batches
SET batch_number = CAST(batch_id AS CHAR)
WHERE batch_number IS NULL OR TRIM(batch_number) = '';

SET @has_batch_number_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'batches'
      AND index_name = 'uq_batches_batch_number'
);
SET @sql := IF(
    @has_batch_number_index = 0,
    'ALTER TABLE batches ADD UNIQUE KEY uq_batches_batch_number (batch_number)',
    'SELECT 1'
);
PREPARE batch_number_index_stmt FROM @sql;
EXECUTE batch_number_index_stmt;
DEALLOCATE PREPARE batch_number_index_stmt;