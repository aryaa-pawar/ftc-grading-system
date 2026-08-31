<?php

require_once __DIR__ . '/../config/database.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS scrutiny_cases (
    case_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    paper_id INT NULL,
    entry_id INT NOT NULL,
    flagged_by_user_id INT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('awaiting_admin','awaiting_recheck','verified') NOT NULL DEFAULT 'awaiting_admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_scrutiny_cases_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_cases_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_cases_entry FOREIGN KEY (entry_id) REFERENCES faculty_marks_entry(entry_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_cases_user FOREIGN KEY (flagged_by_user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_scrutiny_cases_status (status, updated_at),
    INDEX idx_scrutiny_cases_target (student_id, subject_id, entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS scrutiny_mark_change_log (
    change_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    entry_id INT NOT NULL,
    old_marks DECIMAL(5,2) NULL,
    new_marks DECIMAL(5,2) NOT NULL,
    changed_by_user_id INT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'scrutiny_correction',
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_scrutiny_log_case FOREIGN KEY (case_id) REFERENCES scrutiny_cases(case_id) ON DELETE RESTRICT,
    CONSTRAINT fk_scrutiny_log_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_log_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_log_entry FOREIGN KEY (entry_id) REFERENCES faculty_marks_entry(entry_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_log_user FOREIGN KEY (changed_by_user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_scrutiny_log_case (case_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS scrutiny_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    change_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    old_marks DECIMAL(5,2) NULL,
    new_marks DECIMAL(5,2) NOT NULL,
    status ENUM('awaiting_recheck','verified') NOT NULL DEFAULT 'awaiting_recheck',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    verified_at DATETIME NULL,
    CONSTRAINT fk_scrutiny_notifications_case FOREIGN KEY (case_id) REFERENCES scrutiny_cases(case_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_notifications_change FOREIGN KEY (change_id) REFERENCES scrutiny_mark_change_log(change_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_notifications_user FOREIGN KEY (recipient_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_notifications_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_scrutiny_notifications_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    INDEX idx_scrutiny_notifications_user (recipient_user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "Scrutiny edit tracking schema ready.\n";
