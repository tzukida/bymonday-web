<?php
// ============================================================
// shop/config/email_config.php
// Branch: dantes/account-verification
//
// Uses PHPMailer via Composer. If not installed yet, run:
//   composer require phpmailer/phpmailer
// inside your project root.
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Adjust path if your vendor folder is elsewhere
$vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// ── SMTP Credentials ─────────────────────────────────────────
// Store these in a .env file or server environment variables
// in production. Never hard-code real credentials in version
// control.
define('MAIL_HOST',       getenv('MAIL_HOST')       ?: 'smtp.gmail.com');
define('MAIL_PORT',       getenv('MAIL_PORT')       ?: 587);
define('MAIL_USERNAME',   getenv('MAIL_USERNAME')   ?: 'your_gmail@gmail.com');
define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD')   ?: 'your_app_password');   // Gmail App Password
define('MAIL_FROM_NAME',  getenv('MAIL_FROM_NAME')  ?: 'Coffee by Monday Mornings');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'your_gmail@gmail.com');

// ── Token TTL ────────────────────────────────────────────────
define('VERIFICATION_TOKEN_EXPIRY_HOURS', 24);

// ── Helper: create a configured PHPMailer instance ───────────
function createMailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    return $mail;
}

// ── Helper: send verification email ─────────────────────────
/**
 * @param string $toEmail    Recipient email address
 * @param string $toName     Recipient full name
 * @param string $token      Raw verification token
 * @return bool              true on success, false on failure
 */
function sendVerificationEmail(string $toEmail, string $toName, string $token): bool
{
    $verifyUrl = BASE_URL . '/verify_email.php?token=' . urlencode($token);
    $expiry    = VERIFICATION_TOKEN_EXPIRY_HOURS . ' hours';

    $subject = 'Verify your Coffee by Monday Mornings account';

    $body = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <style>
        body { font-family: Arial, sans-serif; background: #f5f0eb; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff;
                   border-radius: 10px; overflow: hidden;
                   box-shadow: 0 2px 8px rgba(0,0,0,.12); }
        .header  { background: #3b2008; padding: 28px 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; letter-spacing: .5px; }
        .body    { padding: 32px; color: #333; line-height: 1.7; }
        .btn     { display: inline-block; margin: 24px 0; padding: 14px 32px;
                   background: #3b2008; color: #fff !important; text-decoration: none;
                   border-radius: 6px; font-weight: bold; font-size: 15px; }
        .note    { font-size: 13px; color: #888; margin-top: 20px; }
        .footer  { background: #f5f0eb; padding: 16px 32px; text-align: center;
                   font-size: 12px; color: #aaa; }
      </style>
    </head>
    <body>
      <div class="wrapper">
        <div class="header">
          <h1>☕ Coffee by Monday Mornings</h1>
        </div>
        <div class="body">
          <p>Hi <strong>{$toName}</strong>,</p>
          <p>Thanks for creating an account! Please verify your email address so you can
             start placing orders.</p>
          <p style="text-align:center;">
            <a href="{$verifyUrl}" class="btn">Verify My Email</a>
          </p>
          <p>Or copy and paste this link into your browser:</p>
          <p style="word-break:break-all;font-size:13px;color:#555;">{$verifyUrl}</p>
          <p class="note">This link expires in <strong>{$expiry}</strong>.
             If you didn't create an account, you can safely ignore this email.</p>
        </div>
        <div class="footer">© Coffee by Monday Mornings · All rights reserved</div>
      </div>
    </body>
    </html>
    HTML;

    try {
        $mail = createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = "Hi {$toName},\n\nVerify your account:\n{$verifyUrl}\n\nLink expires in {$expiry}.";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('[EMAIL] Verification send failed: ' . $e->getMessage());
        return false;
    }
}
