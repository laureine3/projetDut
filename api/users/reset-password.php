<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";
require_once "../../config/mail.php";

requireRole('admin');

header('Content-Type: application/json');

$id = $_POST['id'] ?? null;

if(!$id){
    echo json_encode(["status"=>"error","message"=>"ID invalide"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$newPassword = bin2hex(random_bytes(4));
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password=? WHERE id=?")
    ->execute([$hashed,$id]);

/* AUDIT */
$pdo->prepare("
INSERT INTO audit_logs (action_type, description, logged_at)
VALUES (?,?,NOW())
")->execute([
"reset_password",
"Admin ID ".$_SESSION['user_id']." a réinitialisé le mot de passe de ".$user['email']
]);

/* MAIL */
$message = "
Bonjour {$user['name']},

Votre mot de passe a été réinitialisé par l'administration.

Nouveau mot de passe : {$newPassword}

Nous vous recommandons de le modifier après connexion.

Cordialement,
L'équipe Gestidoc
";

sendSystemMail(
$user['email'],
$user['name'],
"Réinitialisation mot de passe - Gestidoc",
nl2br($message)
);

echo json_encode([
"status"=>"success",
"message"=>"Mot de passe réinitialisé et envoyé par email"
]);