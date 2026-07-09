<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

define('BASE_URL','/' . basename(dirname(__DIR__)));
/*
|--------------------------------------------------------------------------
| Vérifier si connecté
|--------------------------------------------------------------------------
*/
function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location:" .BASE_URL. "/auth/login.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Vérifier rôle
|--------------------------------------------------------------------------
*/
function requireRole($role)
{
    requireLogin();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header("Location:" .BASE_URL. "/auth/login.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Vérifier plusieurs rôles
|--------------------------------------------------------------------------
*/
function requireAnyRole(array $roles)
{
    requireLogin();

    if (!in_array($_SESSION['role'], $roles)) {
        header("Location:" .BASE_URL. "/auth/login.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Vérifier si compte actif (Logout forcé)
|--------------------------------------------------------------------------
*/
function checkIfActive()
{
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si utilisateur supprimé OU désactivé
    if (!$user || $user['is_active'] == 0) {

        // Nettoyage complet session
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        header("Location:" .BASE_URL. "/auth/login.php?error=compte_desactive");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Forcer changement mot de passe
|--------------------------------------------------------------------------
*/
function enforcePasswordChange()
{
    if (
        isset($_SESSION['force_password_change']) &&
        $_SESSION['force_password_change'] === true
    ) {
        if (basename($_SERVER['PHP_SELF']) !== 'change-password.php') {
            header("Location:" .BASE_URL. "/public/change-password.php");
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Redirection automatique selon rôle
|--------------------------------------------------------------------------
*/
function redirectByRole()
{
    if (!isset($_SESSION['role'])) {
        return;
    }

    switch ($_SESSION['role']) {

        case 'admin':
            header("Location:" .BASE_URL. "/public/admin/dashboard.php");
            break;

        case 'agent':
            header("Location:" .BASE_URL. "/public/agent/dashboard.php");
            break;

        case 'user':
            header("Location:" .BASE_URL. "/public/user/upload_document.php");
            break;

        default:
            header("Location:" .BASE_URL. "/auth/login.php");
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| Initialisation automatique protections
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {
    checkIfActive();        // 🔥 Logout forcé si désactivé
    enforcePasswordChange();
}