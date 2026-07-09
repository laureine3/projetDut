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

if(!$user){
    echo json_encode(["status"=>"error","message"=>"Utilisateur introuvable"]);
    exit;
}

$newStatus = $user['is_active'] ? 0 : 1;

$pdo->prepare("UPDATE users SET is_active=? WHERE id=?")
    ->execute([$newStatus,$id]);

/* ================= AUDIT ================= */
$action = $newStatus ? "activation_compte" : "desactivation_compte";
$description = "Admin ID ".$_SESSION['user_id']." a ".$action." le compte de ".$user['email'];

$pdo->prepare("
INSERT INTO audit_logs (action_type, description, logged_at)
VALUES (?,?,NOW())
")->execute([$action,$description]);

/* ================= MAIL ================= */
if(!$newStatus){

$message = "
Bonjour {$user['name']},

Votre compte a été désactivé.

Vous ne pourrez plus accéder au système avant sa réactivation.

Cordialement,
L'équipe Gestidoc
";

sendSystemMail(
$user['email'],
$user['name'],
"Désactivation de compte - Gestidoc",
nl2br($message)
);

}else{

$message = "
Bonjour {$user['name']},

Votre compte a été réactivé.

Vous pouvez maintenant accéder au système.

Cordialement,
L'équipe Gestidoc
";

sendSystemMail(
$user['email'],
$user['name'],
"Réactivation de compte - Gestidoc",
nl2br($message)
);

}

/* ================= FORCE LOGOUT ================= */
if(!$newStatus){
$pdo->prepare("DELETE FROM user_sessions WHERE user_id=?")->execute([$id]);
}

echo json_encode([
"status"=>"success",
"message"=>$newStatus ? "Compte réactivé" : "Compte désactivé"
]);