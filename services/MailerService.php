<?php
// services/MailerService.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailerService {

    private static function ensureLogDirectory(): void {
        $logDirectory = __DIR__ . '/../storage/logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }
    }

    private static function createMailer(): PHPMailer {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME');
        $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD');
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT'));

        $encryption = strtolower($_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION'));
        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS'),
            $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME')
        );

        return $mail;
    }

    /**
     * Send OTP Verification Email
     */
    public static function sendOtpEmail(string $toEmail, string $toName, string $otpCode): bool {
        $mail = null;
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'IMD Portal - Your 2FA Login OTP Code';

            $mail->Body = '
            <div style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-top: 4px solid #003366; border-radius: 6px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="color: #003366; margin-top: 0;">India Meteorological Department</h2>
                    <p style="font-size: 14px;">Dear <strong>' . e($toName) . '</strong>,</p>
                    <p style="font-size: 14px;">Your One-Time Password (OTP) for logging into the IMD Scholar Portal is:</p>
                    <div style="text-align: center; margin: 25px 0;">
                        <span style="font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #003366; background: #eef2f7; padding: 10px 25px; border-radius: 4px; display: inline-block;">' . e($otpCode) . '</span>
                    </div>
                    <p style="font-size: 13px; color: #666;">This OTP is valid for <strong>5 minutes</strong>. Do not share this code with anyone.</p>
                    <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;">
                    <p style="font-size: 11px; color: #888;">This is an automated message from the IMD Training Division Portal. Please do not reply to this email.</p>
                </div>
            </div>';

            $mail->send();
            return true;
        } catch (Exception $e) {
            $smtpError = $mail ? $mail->ErrorInfo : 'Mailer creation failed';
            self::ensureLogDirectory();
            error_log("OTP Mailer Failure [To: {$toEmail}]: " . $e->getMessage() . " | SMTP Error: " . $smtpError . "\n", 3, __DIR__ . '/../storage/logs/app_error.log');
            return false;
        }
    }

    /**
     * Send Account Approval Email
     */
    public static function sendApprovalEmail(string $toEmail, string $toName): bool {
        $mail = null;
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'IMD Portal - Account Registration Approved';

            $mail->Body = '
            <div style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-top: 4px solid #28a745; border-radius: 6px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="color: #003366; margin-top: 0;">Registration Approved</h2>
                    <p style="font-size: 14px;">Dear <strong>' . e($toName) . '</strong>,</p>
                    <p style="font-size: 14px;">We are pleased to inform you that your registration for the IMD Research Portal has been <strong>Approved</strong> by the administrator.</p>
                    <p style="font-size: 14px;">You can now log into your portal account using your email and password.</p>
                    <div style="margin: 25px 0;">
                        <a href="' . baseUrl('login.php') . '" style="background: #003366; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">Log In Now</a>
                    </div>
                    <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;">
                    <p style="font-size: 11px; color: #888;">India Meteorological Department - Training Division</p>
                </div>
            </div>';

            $mail->send();
            return true;
        } catch (Exception $e) {
            $smtpError = $mail ? $mail->ErrorInfo : 'Mailer creation failed';
            self::ensureLogDirectory();
            error_log("Approval Mailer Failure [To: {$toEmail}]: " . $e->getMessage() . " | SMTP Error: " . $smtpError . "\n", 3, __DIR__ . '/../storage/logs/app_error.log');
            return false;
        }
    }

    /**
     * Send Account Rejection Email
     */
    public static function sendRejectionEmail(string $toEmail, string $toName, string $reason): bool {
        $mail = null;
        try {
            $mail = self::createMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'IMD Portal - Account Registration Update';

            $mail->Body = '
            <div style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-top: 4px solid #dc3545; border-radius: 6px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2 style="color: #dc3545; margin-top: 0;">Registration Status Update</h2>
                    <p style="font-size: 14px;">Dear <strong>' . e($toName) . '</strong>,</p>
                    <p style="font-size: 14px;">Your registration for the IMD Student & Research Portal could not be approved at this time.</p>
                    <div style="background-color: #fff3f3; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0;">
                        <p style="font-size: 13px; margin: 0; color: #842029;"><strong>Reason:</strong> ' . nl2br(e($reason)) . '</p>
                    </div>
                    <p style="font-size: 13px; color: #666;">If you believe this is an error or need further clarification, please contact the IMD Training Division.</p>
                    <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;">
                    <p style="font-size: 11px; color: #888;">India Meteorological Department - Training Division</p>
                </div>
            </div>';

            $mail->send();
            return true;
        } catch (Exception $e) {
            $smtpError = $mail ? $mail->ErrorInfo : 'Mailer creation failed';
            self::ensureLogDirectory();
            error_log("Rejection Mailer Failure [To: {$toEmail}]: " . $e->getMessage() . " | SMTP Error: " . $smtpError . "\n", 3, __DIR__ . '/../storage/logs/app_error.log');
            return false;
        }
    }

}
