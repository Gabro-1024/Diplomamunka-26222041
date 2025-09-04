<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';

function sendRegistrationEmail($toEmail, $toName, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth = true;
        $mail->Username = 'ddd5c19228d753';
        $mail->Password = '138a3f6bfe0c20';
        $mail->Port = 2525;
        $mail->SMTPSecure = 'tls';
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom('noreply@ticketsatgabor.com', 'Tickets @ Gábor');
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - Tickets @ Gábor';
        
        $verificationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]/php/verify-email.php?token=$token";
        
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