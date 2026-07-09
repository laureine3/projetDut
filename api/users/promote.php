<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";
require_once "../../config/mail.php";

requireRole('admin');
header('Content-Type: application/json');

$id = $_POST['id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user || $user['role'] !== 'agent'){
    echo json_encode(["status"=>"error","message"=>"Promotion impossible"]);
    exit;
}

$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

if($adminCount >= 3){
    echo json_encode(["status"=>"error","message"=>"Limite d'admins atteinte"]);
    exit;
}

$pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$id]);

/* AUDIT */
$pdo->prepare("
INSERT INTO audit_logs (action_type, description, logged_at)
VALUES (?,?,NOW())
")->execute([
"promotion_admin",
"Admin ID ".$_SESSION['user_id']." a promu ".$user['email']." en admin"
]);

/* MAIL */
$message = "
Bonjour {$user['name']},

Félicitations !

Vous avez été promu administrateur du système Gestidoc.

Cordialement,
L'équipe Gestidoc
";

sendSystemMail(
$user['email'],
$user['name'],
"Promotion en Administrateur - Gestidoc",
nl2br($message)
);

echo json_encode(["status"=>"success","message"=>"Utilisateur promu admin"]);