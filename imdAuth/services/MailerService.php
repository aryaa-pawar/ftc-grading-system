<?php
// services/MailerService.php

require_once __DIR__ . '/../includes/functions.php';

class MailerService {

    private static function ensureLogDirectory(): void {
        $logDirectory = __DIR__ . '/../storage/logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }
    }

    private static function sendBrevoEmail(string $toEmail, string $toName, string $subject, string $htmlContent, string $failureLabel): bool {
        $apiKey = $_ENV['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
        $senderEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS');
        $senderName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME');
        $logFile = __DIR__ . '/../storage/logs/app_error.log';
        $maskedEmail = self::maskedEmail($toEmail);
        error_log("{$failureLabel} attempt started [Recipient: {$maskedEmail}]");

        if (!is_string($apiKey) || trim($apiKey) === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL) || !is_string($senderName) || trim($senderName) === '') {
            error_log("{$failureLabel} failed [Recipient: {$maskedEmail}]: email configuration is missing or invalid");
            self::ensureLogDirectory();
            error_log("{$failureLabel} configuration is missing or invalid.\n", 3, $logFile);
            return false;
        }

        $payload = json_encode([
            'sender' => ['name' => $senderName, 'email' => $senderEmail],
            'to' => [['email' => $toEmail, 'name' => $toName]],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            error_log("{$failureLabel} failed [Recipient: {$maskedEmail}]: request body encoding failed");
            self::ensureLogDirectory();
            error_log("{$failureLabel} failed to encode request body.\n", 3, $logFile);
            return false;
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['api-key: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        error_log("{$failureLabel} provider response [Recipient: {$maskedEmail}, HTTP: {$httpCode}]");

        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            error_log("{$failureLabel} success [Recipient: {$maskedEmail}, HTTP: {$httpCode}]");
            return true;
        }

        $safeMessage = $curlError !== '' ? $curlError : 'Brevo API request failed';
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
                $safeMessage = preg_replace('/[^\x20-\x7E]/', '', $decoded['message']);
            }
        }
        self::ensureLogDirectory();
        error_log("{$failureLabel} provider error [Recipient: {$maskedEmail}, HTTP: {$httpCode}]: {$safeMessage}");
        error_log("{$failureLabel} failed [HTTP {$httpCode}]: {$safeMessage}\n", 3, $logFile);
        return false;
    }

    private static function maskedEmail(string $email): string {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return '[invalid-email]';
        $local = $parts[0];
        return (strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2) . '***') . '@' . $parts[1];
    }

    /**
     * Send OTP Verification Email
     */
    public static function sendOtpEmail(string $toEmail, string $toName, string $otpCode): bool {
        try {
            $htmlContent = '
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

            return self::sendBrevoEmail($toEmail, $toName, 'IMD Portal - Your 2FA Login OTP Code', $htmlContent, 'OTP Mailer');
        } catch (Exception $e) {
            self::ensureLogDirectory();
            error_log("OTP Mailer Failure [To: {$toEmail}]: " . $e->getMessage() . "\n", 3, __DIR__ . '/../storage/logs/app_error.log');
            return false;
        }
    }

    /**
     * Send Account Approval Email
     */
    public static function sendApprovalEmail(string $toEmail, string $toName): bool {
        try {
            $htmlContent = '
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

            return self::sendBrevoEmail($toEmail, $toName, 'IMD Portal - Account Registration Approved', $htmlContent, 'Approval Mailer');
        } catch (Exception $e) {
            self::ensureLogDirectory();
            error_log("Approval Mailer Failure [To: {$toEmail}]: " . $e->getMessage() . "\n", 3, __DIR__ . '/../storage/logs/app_error.log');
            return false;
        }
    }

    /**
     * Send Account Rejection Email
     */
    public static function sendRejectionEmail(string $toEmail, string $toName, string $reason): bool {
        try {
            $htmlContent = '
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

            return self::sendBrevoEmail($toEmail, $toName, 'IMD Portal - Account Registration Update', $htmlContent, 'Rejection Mailer');
        } catch (Exception $e) {
            self::ensureLogDirectory();
            error_log("Rejection Mailer Failure [To: {$toEmail}]: " . $e->getMessage() . "\n", 3, __DIR__ . '/../storage/logs/app_error.log');
            return false;
        }
    }

}
