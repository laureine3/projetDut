<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/audit.php";

header('Content-Type: application/json');

$current = $_POST['current_password'];
$new     = $_POST['new_password'];
$confirm = $_POST['confirm_password'];

$stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!password_verify($current, $user['password'])) {
    echo json_encode(["status"=>"error","message"=>"Mot de passe incorrect"]);
    exit;
}

if ($new !== $confirm) {
    echo json_encode(["status"=>"error","message"=>"Les mots de passe ne correspondent pas"]);
    exit;
}

$hashed = password_hash($new, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password=?, must_change_password=0 WHERE id=?")
    ->execute([$hashed,$_SESSION['user_id']]);

logAudit(
    $_SESSION['user_id'],
    "PASSWORD_CHANGE",
    $_SESSION['user_id'],
    "Mot de passe modifié par {$_SESSION['name']}"
);

echo json_encode(["status"=>"success"]);