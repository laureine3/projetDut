<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendSystemMail($toEmail, $toName, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        // Configuration SMTP Mailtrap
        $mail->isSMTP();
        $mail->Host       = '';
        $mail->SMTPAuth   = true;
        $mail->Username   = '';
        $mail->Password   = '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Expéditeur
        $mail->setFrom('no-reply@gestidoc.com', 'Gestidoc Universitaire');

        // Destinataire
        $mail->addAddress($toEmail, $toName);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}