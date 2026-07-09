<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/audit.php";

header('Content-Type: application/json');

$name  = trim($_POST['name']);
$email = trim($_POST['email']);

if (!$name || !$email) {
    echo json_encode(["status"=>"error","message"=>"Champs invalides"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
$stmt->execute([$name,$email,$_SESSION['user_id']]);

logAudit(
    $_SESSION['user_id'],
    "PROFILE_UPDATE",
    $_SESSION['user_id'],
    "Profil modifié par {$_SESSION['name']}"
);

$_SESSION['name'] = $name;

echo json_encode(["status"=>"success"]);