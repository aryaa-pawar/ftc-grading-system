<?php

require_once __DIR__ . '/../config/database.php';

$columns = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('verification_status', $columns, true)) {
    $pdo->exec("ALTER TABLE students ADD COLUMN verification_status ENUM('correct','wrong') NULL AFTER scrutiny_remarks");
}

if (!in_array('verification_notified_at', $columns, true)) {
    $pdo->exec("ALTER TABLE students ADD COLUMN verification_notified_at DATETIME NULL AFTER verification_status");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$notificationColumns = $pdo->query("SHOW COLUMNS FROM admin_notifications")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('entry_id', $notificationColumns, true)) {
    $pdo->exec("ALTER TABLE admin_notifications ADD COLUMN entry_id INT NULL AFTER batch_id");
}

$markColumns = $pdo->query("SHOW COLUMNS FROM faculty_marks_entry")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('verification_status', $markColumns, true)) {
    $pdo->exec("ALTER TABLE faculty_marks_entry ADD COLUMN verification_status ENUM('correct','wrong') NULL");
}
if (!in_array('verification_notified_at', $markColumns, true)) {
    $pdo->exec("ALTER TABLE faculty_marks_entry ADD COLUMN verification_notified_at DATETIME NULL");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS correction_notifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$correctionColumns = $pdo->query("SHOW COLUMNS FROM correction_notifications")->fetchAll(PDO::FETCH_COLUMN);
foreach ([
    'original_mark' => 'ALTER TABLE correction_notifications ADD COLUMN original_mark DECIMAL(5,2) NULL AFTER entry_id',
    'corrected_mark' => 'ALTER TABLE correction_notifications ADD COLUMN corrected_mark DECIMAL(5,2) NULL AFTER original_mark',
    'corrected_by_faculty_id' => 'ALTER TABLE correction_notifications ADD COLUMN corrected_by_faculty_id INT NULL AFTER corrected_mark',
    'completed_at' => 'ALTER TABLE correction_notifications ADD COLUMN completed_at DATETIME NULL AFTER sent_at'
] as $column => $statement) {
    if (!in_array($column, $correctionColumns, true)) {
        $pdo->exec($statement);
    }
}

echo "Verification notification schema ready.\n";
