# IMD Research & Guide Management Portal — Auth, 2FA & Profile Module (Phase 1)

Production-ready Authentication, Approval Workflow, 2-Factor Authentication (OTP), Role-Based Access Control, and Profile Management System built for the India Meteorological Department (IMD) Training Division.

---

## Technical Stack & Architecture

- **Language & Core Logic:** PHP 8.x (Vanilla PHP with lightweight MVC architecture)
- **Database:** MySQL / MariaDB via PDO (`PDO::ATTR_EMULATE_PREPARES => false`, UTF-8)
- **Email Delivery:** PHPMailer via SMTP (Configured for Mailtrap Sandbox testing by default)
- **UI Framework:** Bootstrap 5 with custom Government-Portal aesthetic CSS
- **Dependencies:** `phpmailer/phpmailer`, `vlucas/phpdotenv` (managed via Composer)

---

## Features Implemented

1. **3 System Roles:**
   - `guide`: Self-registers (IMD Scientist / Faculty Supervisor).
   - `dept_admin`: Approves/rejects registrations, views directory. Created by Super Admin.
   - `super_admin`: Full system oversight, account suspension, role management, dept admin creation.

2. **Registration & Admin Approval Workflow:**
   - Public Guide registration form with input validation (`full_name`, `email`, `phone`, `department`, `password`).
   - New guide registrations land in `pending_approval` status (login blocked).
   - Rejection requires a reason, stored in DB and sent via email notification.

3. **Two-Step Authentication (2FA) with Email OTP:**
   - **Step 1 (Password):** Bcrypt password hashing (`password_hash` / `password_verify`). 5 failed password attempts trigger a 15-minute account lockout.
   - **Step 2 (OTP Verification):** 6-digit numeric OTP hashed at rest with 5-minute expiry. Enforces a 3-attempt limit and a 60-second resend cooldown.
   - **Dev Mode OTP Fallback:** If email delivery is unconfigured or fails when `APP_ENV=development`, the 6-digit OTP code is displayed directly in the on-screen alert banner (`[DEV MODE OTP: XXXXXX]`) for effortless local testing.
   - **Session Security:** Full authenticated session is ONLY created after successful OTP verification (`session_regenerate_id(true)`). `pending_2fa_user_id` cannot access protected routes.

4. **Profile Management & Secure Avatar Upload:**
   - Guide profile editing (`full_name`, `phone`, `department`, `dob`, `address`).
   - Profile avatar upload with MIME type validation (`finfo` checking `image/jpeg` & `image/png`), 2MB size cap, and randomized filename storage outside direct web script execution.

5. **Security Baseline:**
   - PDO prepared statements exclusively.
   - `htmlspecialchars()` output escaping across all dynamic values.
   - CSRF protection tokens validated on every POST action.
   - Comprehensive audit logging (`audit_logs`) tracking logins, OTP triggers, registrations, and approvals.

---

## Setup & Installation Instructions (XAMPP Environment)

### Step 1: Clone / Copy Workspace Files
Ensure project files are located in your XAMPP `htdocs` folder:
`C:\xampp\htdocs\imdAuth`

### Step 2: Install Composer Dependencies
Open a command prompt in the project root directory and run:
```bash
composer install
```
*(If composer is run directly via XAMPP PHP: `C:\xampp\php\php.exe composer.phar install`)*

### Step 3: Database Setup
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or MySQL CLI.
2. Create and import the database schema by importing `database/schema.sql`.
   - SQL file creates database `ftc_grading` automatically.
   - SQL file seeds the initial **Super Admin** account.

### Step 4: Environment Configuration (`.env`)
Copy `.env.example` to `.env` if not already present:
```bash
cp .env.example .env
```
Update `.env` with your local database and SMTP credentials:
```ini
APP_ENV=development
APP_URL=http://localhost/ftc_grading
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ftc_grading
DB_USER=root
DB_PASS=arya123

MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=b695c3001@smtp-brevo.com
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=aryaaa.pawar@gmail.com
MAIL_FROM_NAME="IMD-PUNE"
```

---

## Initial Super Admin Credentials

- **Email:** `aryaaa.pawar@gmail.com`
- **Password:** `12345678`

---

## File Structure Overview

```
imdAuth/
├── config/
│   └── database.php                # PDO Connection Singleton
├── includes/
│   ├── auth_guard.php              # Auth & Role Enforcement Guard
│   ├── csrf.php                    # CSRF Helper & Middleware
│   └── functions.php               # Helpers & Audit Logger
├── services/
│   └── MailerService.php           # PHPMailer Email Wrapper
├── guide/
│   ├── dashboard.php               # Guide Dashboard
│   └── profile.php                 # Guide Profile Editor
├── admin/
│   ├── dashboard.php               # Admin Overview & Dept Admin Form
│   ├── pending_approvals.php       # Approval Queue & Rejection Modal
│   └── manage_users.php            # User Directory & Role Control
├── assets/
│   ├── css/style.css               # IMD Government Aesthetic CSS
│   └── js/main.js                  # Resend Timer & Interactive Scripts
├── storage/
│   ├── uploads/profile_photos/     # Avatar Storage with Script Block .htaccess
│   └── logs/app_error.log          # Application Error Log
├── login.php                       # Step 1 Password Login
├── verify-otp.php                  # Step 2 OTP 2FA Verification
├── register.php                    # Public Guide Registration Form
├── logout.php                      # Session Destruction
├── serve_photo.php                 # Protected Avatar Delivery
├── database/schema.sql             # Full Database DDL + Seed Data
├── composer.json                   # PHP Dependencies
├── .env.example                    # Environment Template
└── README.md                       # Setup Documentation
```

---

## Verification & Testing Guide

1. **Test Guide Registration:**
   - Go to `http://localhost/imdAuth/register.php`.
   - Register a Guide (Full Name, Email, Phone, Department, Password). Verify account lands in `pending_approval`.
2. **Test Admin Approval:**
   - Log in as Super Admin (`aryaaa.pawar@gmail.com` / `12345678`).
   - Go to **Pending Approvals** queue.
   - Click **Approve** on the Guide application.
3. **Test 2FA Login & Lockout:**
   - Log in with the approved Guide email or Super Admin (`aryaaa.pawar@gmail.com` / `12345678`).
   - Copy the 6-digit OTP code sent via Mailtrap (or the `[DEV MODE OTP: XXXXXX]` alert banner in development).
   - Enter OTP code to access Dashboard.
4. **Test Access Control Guard:**
   - Attempt to access `http://localhost/imdAuth/admin/dashboard.php` as a guide. Verify **403 Forbidden** page is displayed.
