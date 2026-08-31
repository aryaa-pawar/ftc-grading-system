<?php
/**
 * teacher/auth_guard.php
 * Include at the top of every teacher panel page.
 * Redirects to login if no valid faculty session exists.
 */

require_once __DIR__ . '/../includes/auth.php';

$user = require_permission('marks.enter');
if (empty($_SESSION['faculty_id']) || !is_numeric($_SESSION['faculty_id'])) {
    auth_redirect('teacher/login.php?error=Faculty+profile+is+not+linked');
}
