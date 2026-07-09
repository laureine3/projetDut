<?php
session_start();
require_once "../../../config/database.php";
require_once "../../../config/security.php";
require_once "../../../config/mailer.php";
require_once "../../../config/audit.php";

if ($_SESSION['role'] !== 'admin') {
    exit(json_encode(["status" => "error"]));
}

$userId = intval($_POST['user_id']);

$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$tempPassword = generateTemporaryPassword();
$hashed = password_hash($tempPassword, PASSWORD_DEFAULT);

$update = $pdo->prepare("
    UPDATE users
    SET password = ?, must_change_password = 1
    WHERE id = ?
");

$update->execute([$hashed, $userId]);

$message = "
Bonjour {$user['name']},

Votre mot de passe a été réinitialisé.

Nouveau mot de passe temporaire : $tempPassword

Vous devrez obligatoirement le modifier lors de votre prochaine connexion.
";

sendSystemMail($user['email'], $user['name'], "Réinitialisation mot de passe - Gestidoc", nl2br($message));

logAudit(
    $_SESSION['user_id'],
    "PASSWORD_RESET",
    $userId,
    $_SESSION['name'] . " a réinitialisé le mot de passe de " . $user['name']
);

echo json_encode(["status" => "success"]);