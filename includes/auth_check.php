<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Authentication Check
|--------------------------------------------------------------------------
| This file protects pages that require a logged-in Admin.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/auth.php';

$user = require_permission('admin.access');