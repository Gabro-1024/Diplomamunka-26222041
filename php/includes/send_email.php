<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';

// Load .env from project root if present
$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot && file_exists($projectRoot . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
    $dotenv->load();
}
function makeConfiguredMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    // Server settings
    $mail->isSMTP();
    $mail->Host = $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST') ?? 'sandbox.smtp.mailtrap.io';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?? '';
    $mail->Password = $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?? '';
    $mail->Port = (int)($_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?? 2525);
    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?? PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';

    // From
    $fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?? 'noreply@ticketsatgabor.com';
    $fromName  = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?? 'Tickets @ Gábor';
    $mail->setFrom($fromEmail, $fromName);

    return $mail;
}

function sendPasswordResetEmail($toEmail, $toName, $token, $resetLink)
{
    $mail = makeConfiguredMailer();

    try {
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Tickets @ Gábor';

        $mail->Body = "
            <h2>Password Reset Request</h2>
            <p>Hello $toName,</p>
            <p>We received a request to reset your password. Click the button below to set a new password:</p>
            <p>
                <a href='$resetLink' style='background: #2210FF; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    Reset Password
                </a>
            </p>
            <p>Or copy and paste this link into your browser:<br>
            <a href='$resetLink'>$resetLink</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, you can safely ignore this email.</p>
        ";

        $mail->AltBody = "Password Reset Request\n\n" .
            "Hello $toName,\n\n" .
            "We received a request to reset your password. Please visit the following link to set a new password:\n\n" .
            "$resetLink\n\n" .
            "This link will expire in 1 hour.\n\n" .
            "If you didn't request this, you can safely ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password reset email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
/**
 * Send purchase confirmation with ticket attachments.
 *
 * @param string $toEmail Buyer email address.
 * @param string $toName  Buyer name (optional).
 * @param array  $ticketFiles Array of absolute or relative file paths to attach (PNG QR codes).
 * @param array  $meta Optional associative array: ['event_name'=>..., 'event_date'=>..., 'purchase_id'=>..., 'amount'=>..., 'currency'=>...]
 * @return bool
 */
function sendTicketsEmail(string $toEmail, string $toName = '', array $ticketFiles = [], array $meta = []): bool {
    $mail = makeConfiguredMailer();

    try {
        $mail->addAddress($toEmail, $toName ?: $toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your Tickets - Tickets @ Gábor';

        $eventName = $meta['event_name'] ?? 'Your Event';
        $eventDate = $meta['event_date'] ?? '';
        $purchaseId = $meta['purchase_id'] ?? '';
        $amount = $meta['amount'] ?? '';
        $currency = strtoupper((string)($meta['currency'] ?? 'HUF'));
        $count = count($ticketFiles);

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; line-height:1.5;'>
              <h2 style='margin:0 0 10px;'>Thank you for your purchase!</h2>
              <p>Hello " . htmlspecialchars($toName ?: $toEmail) . ",</p>
              <p>Your tickets are attached to this email as PNG files.</p>
              <ul style='padding-left:18px;'>
                <li><strong>Event:</strong> " . htmlspecialchars($eventName) . "</li>
                " . ($eventDate !== '' ? "<li><strong>Date:</strong> " . htmlspecialchars($eventDate) . "</li>" : "") . "
                " . ($purchaseId !== '' ? "<li><strong>Order #</strong> " . htmlspecialchars((string)$purchaseId) . "</li>" : "") . "
                " . ($amount !== '' ? "<li><strong>Total:</strong> " . htmlspecialchars((string)$amount) . " " . htmlspecialchars($currency) . "</li>" : "") . "
                <li><strong>Tickets:</strong> " . (int)$count . "</li>
              </ul>
              <p>If you have any issues at the entrance, just show the QR codes to the staff.</p>
              <p>Enjoy the event!<br>Tickets @ Gábor</p>
            </div>
        ";

        $mail->AltBody = "Thank you for your purchase!\n\n"
            . "Event: {$eventName}\n"
            . ($eventDate !== '' ? "Date: {$eventDate}\n" : '')
            . ($purchaseId !== '' ? "Order #: {$purchaseId}\n" : '')
            . ($amount !== '' ? "Total: {$amount} {$currency}\n" : '')
            . "Tickets: {$count}\n\n"
            . "Your tickets are attached as PNG files.\n"
            . "Enjoy the event!";

        // Attach ticket files (if any)
        foreach ($ticketFiles as $path) {
            if (!$path) { continue; }
            $abs = $path;
            if (!preg_match('/^([A-Za-z]:\\\\|\\/)/', $path)) { // not absolute (Windows/Unix)
                $abs = realpath(__DIR__ . '/../' . ltrim($path, '/\\')) ?: (__DIR__ . '/../' . ltrim($path, '/\\'));
            }
            if (is_file($abs)) {
                $filename = basename($abs);
                $mail->addAttachment($abs, $filename);
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Tickets email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendRegistrationEmail($toEmail, $toName, $token) {
    $mail = makeConfiguredMailer();
    
    try {
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - Tickets @ Gábor';
        
        $verificationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]/Diplomamunka-26222041/php/sign-in.php?verify=$token";
        
        $mail->Body = "
            <h2>Welcome to Tickets @ Gábor, $toName!</h2>
            <p>Thank you for registering. Please verify your email by clicking the button below:</p>
            <p>
                <a href='$verificationUrl' style='background: #2210FF; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    Verify Email
                </a>
            </p>
            <p>Or copy and paste this link into your browser:<br>
            <a href='$verificationUrl'>$verificationUrl</a></p>
            <p>If you didn't create an account, you can safely ignore this email.</p>
        ";
        
        $mail->AltBody = "Welcome to Tickets @ Gábor, $toName!\n\n" .
                       "Please verify your email by visiting this link:\n" .
                       "$verificationUrl\n\n" .
                       "If you didn't create an account, you can safely ignore this email.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>