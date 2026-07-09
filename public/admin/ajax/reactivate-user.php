<?php
session_start();
require_once "../../../config/database.php";
require_once "../../../config/audit.php";

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(["status"=>"error"]);
    exit;
}

$userId = intval($_POST['user_id']);

$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$userId]);

$message = "
Bonjour {$user['name']},

Votre compte a été réactivé.

Veuillez donc vous reconnecter afin de reprendre vos activités.
";

sendSystemMail($user['email'], $user['name'], "Réactivation de compte - Gestidoc", nl2br($message));

logAudit(
    $_SESSION['user_id'],
    "USER_REACTIVATION",
    $userId,
    "Compte de {$user['name']} réactivé par {$_SESSION['name']}"
);

echo json_encode(["status"=>"success"]);