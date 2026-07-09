<?php
session_start();

require_once "../config/database.php";
require_once "../config/security.php";
require_once "../config/mail.php";
require_once "../config/audit.php";

/* ================= VERIFICATION SESSION ================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];

/* ================= RECUPERER UTILISATEUR ================= */

$stmt = $pdo->prepare("SELECT name,email,must_change_password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

/* ================= SI PAS OBLIGE DE CHANGER ================= */

if ($user['must_change_password'] == 0) {

    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: agent/dashboard.php");
    }

    exit;
}

/* ================= TRAITEMENT FORMULAIRE ================= */

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newPassword = trim($_POST['new_password'] ?? "");
    $confirmPassword = trim($_POST['confirm_password'] ?? "");

    if ($newPassword === "" || $confirmPassword === "") {

        $error = "Veuillez remplir tous les champs.";

    } elseif ($newPassword !== $confirmPassword) {

        $error = "Les mots de passe ne correspondent pas.";

    } elseif (strlen($newPassword) < 6) {

        $error = "Le mot de passe doit contenir au moins 6 caractères.";

    } else {

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $pdo->prepare("
            UPDATE users
            SET password = ?, must_change_password = 0
            WHERE id = ?
        ");

        $update->execute([$hashed, $userId]);

        /* ================= AUDIT ================= */

        logAudit(
            $_SESSION['user_id'],
            "PASSWORD_CHANGE",
            $userId,
            $_SESSION['name'] . " a modifié son mot de passe"
        );

        /* ================= METTRE A JOUR SESSION ================= */

        $_SESSION['force_password_change'] = false;

        $_SESSION['success_message'] = "Mot de passe modifié avec succès.";

        /* ================= REDIRECTION ================= */

        if ($_SESSION['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: agent/dashboard.php");
        }

        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Changer mot de passe</title>

<style>

body{
font-family: Arial;
background:#f5f6fa;
display:flex;
justify-content:center;
align-items:center;
height:100vh;
}

.container{
background:white;
padding:40px;
border-radius:10px;
width:350px;
box-shadow:0 5px 15px rgba(0,0,0,0.2);
}

h2{
text-align:center;
margin-bottom:20px;
}

input{
width:100%;
padding:10px;
margin-bottom:15px;
border-radius:6px;
border:1px solid #ccc;
}

button{
width:100%;
padding:10px;
border:none;
background:#2a5298;
color:white;
font-weight:bold;
border-radius:6px;
cursor:pointer;
}

button:hover{
background:#1e3c72;
}

.error{
color:red;
margin-bottom:10px;
text-align:center;
}

</style>

</head>

<body>

<div class="container">

<h2>Changer votre mot de passe</h2>

<p>
Bonjour <b><?= htmlspecialchars($user['name']) ?></b>,  
vous devez modifier votre mot de passe pour continuer.
</p>

<?php if($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

<input type="password" name="new_password" placeholder="Nouveau mot de passe" required>

<input type="password" name="confirm_password" placeholder="Confirmer mot de passe" required>

<button type="submit">
Modifier le mot de passe
</button>

</form>

</div>

</body>
</html>