<?php
session_start();
require_once "../../../config/database.php";
require_once "../../../config/security.php";
require_once "../../../config/mailer.php";
require_once "../../../config/audit.php";

if ($_SESSION['role'] !== 'admin') {
    exit(json_encode(["status" => "error"]));
}

$name  = trim($_POST['name']);
$email = trim($_POST['email']);
$role  = trim($_POST['role']);

$tempPassword = generateTemporaryPassword();
$hashed = password_hash($tempPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password, role, must_change_password, created_at)
    VALUES (?, ?, ?, ?, 1, NOW())
");

$stmt->execute([$name, $email, $hashed, $role]);

$userId = $pdo->lastInsertId();

$message = "
Bonjour $name,

Votre compte Gestidoc a été créé.

Email : $email
Mot de passe temporaire : $tempPassword

Vous devrez obligatoirement modifier ce mot de passe à votre première connexion.
";

sendSystemMail($email, $name, "Vos identifiants - Gestidoc", nl2br($message));

logAudit(
    $_SESSION['user_id'],
    "USER_CREATION",
    $userId,
    $_SESSION['name'] . " a créé un compte $role pour $name"
);

echo json_encode(["status" => "success"]);