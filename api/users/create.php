<?php
session_start();

require_once "../../config/database.php";
require_once "../../config/auth.php";
require_once "../../config/mail.php";
require_once "../../config/notifications.php"; // si tu utilises createNotification()

requireRole('admin');

header('Content-Type: application/json');

/* ================= VALIDATION ================= */

$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role  = $_POST['role'] ?? 'agent';

if(empty($name) || empty($email)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Tous les champs sont obligatoires."
    ]);
    exit;
}

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    echo json_encode([
        "status"=>"error",
        "message"=>"Email invalide."
    ]);
    exit;
}

/* ================= EMAIL UNIQUE ================= */

$stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$email]);

if($stmt->fetch()){
    echo json_encode([
        "status"=>"error",
        "message"=>"Cet email existe déjà."
    ]);
    exit;
}

/* ================= LIMITE ADMINS ================= */

if($role === 'admin'){
    $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

    if($adminCount >= 3){
        echo json_encode([
            "status"=>"error",
            "message"=>"Limite maximale d'admins atteinte."
        ]);
        exit;
    }
}

/* ================= CREATION ================= */

$tempPassword = bin2hex(random_bytes(4));
$hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
INSERT INTO users (name, email, password, role, is_active, created_at)
VALUES (?, ?, ?, ?, 1, NOW())
");

$stmt->execute([$name, $email, $hashedPassword, $role]);

$newUserId = $pdo->lastInsertId();

/* ================= AUDIT LOG ================= */

$actionType = "creation_utilisateur";

$description = "Admin ID ".$_SESSION['user_id'].
" a créé le compte ".$email.
" avec le rôle ".$role;

$pdo->prepare("
INSERT INTO audit_logs (action_type, description, logged_at)
VALUES (?, ?, NOW())
")->execute([$actionType, $description]);

/* ================= NOTIFICATION INTERNE ================= */

if(function_exists('createNotification')){
    createNotification(
        $pdo,
        $newUserId,
        "Bienvenue sur Gestidoc",
        "Votre compte a été créé avec succès.",
        "info"
    );
}

/* ================= MAILTRAP ================= */

$message = "
Bonjour {$name},

Votre compte a été créé sur la plateforme Gestidoc.

Identifiants de connexion :

Email : {$email}
Mot de passe temporaire : {$tempPassword}

Nous vous recommandons de modifier votre mot de passe après votre première connexion.

Cordialement,
L'équipe Gestidoc
";

sendSystemMail(
    $email,
    $name,
    "Création de compte - Gestidoc",
    nl2br($message)
);

/* ================= RESPONSE ================= */

echo json_encode([
    "status"=>"success",
    "message"=>"Utilisateur créé et email envoyé."
]);