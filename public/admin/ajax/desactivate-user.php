<?php
session_start();

require_once "../../../config/database.php";
require_once "../../../config/audit.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Accès refusé"]);
    exit;
}

$userId = intval($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(["status" => "error", "message" => "ID invalide"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["status" => "error", "message" => "Utilisateur introuvable"]);
    exit;
}

// Empêcher auto-désactivation
if ($userId == $_SESSION['user_id']) {
    echo json_encode(["status" => "error", "message" => "Vous ne pouvez pas vous désactiver vous-même"]);
    exit;
}

// Désactivation logique
$update = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
$update->execute([$userId]);

$message = "
Bonjour {$user['name']},

Votre compte a été désactivé.

Vous ne pourrez pus accéder au systeme avant sa réactivation.
";

sendSystemMail($user['email'], $user['name'], "Désactivation de compte - Gestidoc", nl2br($message));

logAudit(
    $_SESSION['user_id'],
    "USER_DEACTIVATION",
    $userId,
    "Compte de {$user['name']} désactivé par {$_SESSION['name']}"
);

echo json_encode(["status" => "success"]);