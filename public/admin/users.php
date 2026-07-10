<?php
session_start();

require_once "../../config/database.php";
require_once "../../config/security.php";
require_once "../../config/mail.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit("Accès refusé");
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];
$operator_role = $_SESSION['role'];


/* ================================================= */
/* FONCTION AUDIT INTERNE                            */
/* ================================================= */

function systemAudit($pdo, $operatorId, $operatorRole, $actionType, $targetId, $description)
{
    $stmt = $pdo->prepare("
INSERT INTO audit_logs
(operator_id,operator_role,action_type,target_type,target_id,description,logged_at)
VALUES (?,?,?,?,?,?,NOW())
");
    $stmt->execute([
        $operatorId,
        $operatorRole,
        $actionType,
        "USER",
        $targetId,
        $description
    ]);
}


/* ================================================= */
/* TRAITEMENT AJAX                                   */
/* ================================================= */

if (isset($_POST['action'])) {

    header("Content-Type: application/json");

    $action = $_POST['action'];

    try {

        if ($action === "create_agent") {

            $name = trim($_POST['name']);
            $email = trim($_POST['email']);

            if (!$name || !$email) {
                throw new Exception("Informations invalides");
            }

           # $tempPassword = generateTemporaryPassword();
            $tempPassword = 123456;
            $hashed = password_hash($tempPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
INSERT INTO users (name,email,password,role,is_active,must_change_password,is_system_admin)
VALUES (?,?,?,'agent',1,1,0)
");
            $stmt->execute([$name, $email, $hashed]);
            $userId = $pdo->lastInsertId();

            sendSystemMail(
                $email, $name,
                "Création de compte",
                "Votre mot de passe temporaire est : " . $tempPassword
            );

            systemAudit($pdo, $admin_id, $operator_role, "CREATE_AGENT", $userId,
                $admin_name . " a créé un agent : " . $name);

            echo json_encode([
                "status"       => "success",
                "tempPassword" => $tempPassword,
                "agentName"    => $name
            ]);
            exit;
        }

        if ($action === "reset_password") {

            $userId = intval($_POST['user_id']);

            $stmt = $pdo->prepare("SELECT name,email,is_system_admin FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) throw new Exception("Utilisateur introuvable");
            if ($user['is_system_admin']) throw new Exception("Impossible de modifier le compte admin système");

            $tempPassword = generateTemporaryPassword();
            $hashed = password_hash($tempPassword, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE users SET password=?,must_change_password=1 WHERE id=?");
            $update->execute([$hashed, $userId]);

            sendSystemMail($user['email'], $user['name'], "Reset mot de passe",
                "Votre nouveau mot de passe temporaire est : " . $tempPassword);

            systemAudit($pdo, $admin_id, $operator_role, "RESET_PASSWORD", $userId,
                $admin_name . " a réinitialisé le mot de passe de " . $user['name']);

            echo json_encode(["status" => "success"]);
            exit;
        }

        if ($action === "toggle_status") {

            $userId = intval($_POST['user_id']);

            $stmt = $pdo->prepare("SELECT name,is_active,is_system_admin,role FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) throw new Exception("Utilisateur introuvable");
            if ($user['is_system_admin']) throw new Exception("Impossible de modifier le compte admin système");

            $newStatus = $user['is_active'] ? 0 : 1;

            if ($user['role'] === 'admin' && $newStatus === 0) {
                $count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($count <= 1) throw new Exception("Impossible de désactiver le dernier administrateur actif");
            }

            $update = $pdo->prepare("UPDATE users SET is_active=? WHERE id=?");
            $update->execute([$newStatus, $userId]);

            systemAudit($pdo, $admin_id, $operator_role, "TOGGLE_STATUS", $userId,
                $admin_name . " a " . ($newStatus ? "activé " : "désactivé ") . $user['name']);

            echo json_encode(["status" => "success"]);
            exit;
        }

        if ($action === "promote_user") {

            $userId = intval($_POST['user_id']);

            $stmt = $pdo->prepare("SELECT name,is_system_admin FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) throw new Exception("Utilisateur introuvable");
            if ($user['is_system_admin']) throw new Exception("Impossible de modifier le compte admin système");

            $count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
            if ($count >= 3) throw new Exception("Limite de 3 administrateurs atteinte");

            $update = $pdo->prepare("UPDATE users SET role='admin' WHERE id=?");
            $update->execute([$userId]);

            systemAudit($pdo, $admin_id, $operator_role, "PROMOTE_ADMIN", $userId,
                $admin_name . " a promu " . $user['name'] . " administrateur");

            echo json_encode(["status" => "success"]);
            exit;
        }

        if ($action === "demote_admin") {

            $userId = intval($_POST['user_id']);

            $stmt = $pdo->prepare("SELECT name,is_system_admin FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) throw new Exception("Utilisateur introuvable");
            if ($user['is_system_admin']) throw new Exception("Impossible de rétrograder l'admin système");

            $update = $pdo->prepare("UPDATE users SET role='agent' WHERE id=?");
            $update->execute([$userId]);

            systemAudit($pdo, $admin_id, $operator_role, "DEMOTE_ADMIN", $userId,
                $admin_name . " a rétrogradé " . $user['name'] . " agent");

            echo json_encode(["status" => "success"]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        exit;
    }
}


/* ================================================= */
/* FILTRAGE ET TRI                                   */
/* ================================================= */

$filter = $_GET['filter'] ?? 'all';
$sort   = $_GET['sort']   ?? 'az';

$where = [];
$where[] = "role IN ('admin','agent')";

if ($filter === "admin")    $where[] = "role='admin'";
if ($filter === "agent")    $where[] = "role='agent'";
if ($filter === "active")   $where[] = "is_active=1";
if ($filter === "inactive") $where[] = "is_active=0";

$whereSQL = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$orderSQL = "ORDER BY name ASC";
if ($sort === "za")  $orderSQL = "ORDER BY name DESC";
if ($sort === "new") $orderSQL = "ORDER BY id DESC";
if ($sort === "old") $orderSQL = "ORDER BY id ASC";


/* ================================================= */
/* RECUPERATION UTILISATEURS                         */
/* ================================================= */

$stmt = $pdo->query("
SELECT id,name,email,role,is_active,is_system_admin
FROM users
$whereSQL
$orderSQL
");

$users = $stmt->fetchAll();

/* Compteurs */
$totalUsers   = count($users);
$totalAdmins  = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$totalAgents  = count(array_filter($users, fn($u) => $u['role'] === 'agent'));
$totalActifs  = count(array_filter($users, fn($u) => $u['is_active']));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion utilisateurs — Utilisteurs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

    /* ── Variables ── */
    :root {
        --primary:    #2563eb;
        --sidebar-bg: #1e293b;
        --sidebar-text:#94a3b8;
        --bg-body:    #f5f7fa;
        --text-main:  #0d1b2a;
        --text-muted: #7f8fa4;
        --white:      #ffffff;
        --danger:     #dc2626;
        --success:    #059669;
        --warning:    #d97706;
        --ink:        #0d1b2a;
        --ink-muted:  #7f8fa4;
        --line:       #e8ecf1;
        --surface:    #ffffff;
        --bg:         #f5f7fa;
        --blue:       #2563eb;
        --blue-soft:  #eff4ff;
        --blue-dark:  #1d4ed8;
        --green:      #059669;
        --green-soft: #ecfdf5;
        --amber:      #d97706;
        --amber-soft: #fffbeb;
        --red:        #dc2626;
        --red-soft:   #fef2f2;
        --shadow:     0 4px 16px rgba(13,27,42,.08);
        --r:          10px;
    }

    /* RESET */
    *, *::before, *::after {
        margin: 0; padding: 0;
        box-sizing: border-box;
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
    }

    /* BODY */
    body {
        background: var(--bg);
        color: var(--ink);
        padding: 20px;
        margin: 0;
        font-family: 'DM Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--bg-body);
        color: var(--text-main);
        display: flex;
        min-height: 100vh;
    }

    /* ── Wrapper principal ── */
    .main-wrapper {
        margin-left: 240px;
        flex: 1;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    /* NAV (si nav.php contient sidebar)
    nav, .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 220px;
      height: 100vh;
      background: #0f172a;
      color: white;
    }*/

    /* HEADER */
    header {
        margin-left: 0;
        padding: 16px 22px;
        background: var(--surface);
        border-radius: var(--r);
        box-shadow: var(--shadow);
        border: 1px solid var(--line);
        margin-bottom: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    header h1 {
        font-family: 'DM Serif Display', serif;
        font-size: 20px;
        font-weight: 400;
        color: var(--ink);
    }

    .header-sub {
        font-size: 13px;
        color: var(--ink-muted);
        margin-top: 2px;
    }

    /* ── Badges header ── */
    .header-badges { display: flex; gap: 8px; }

    .hbadge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px;
        font-size: 12px; font-weight: 500;
    }

    .hbadge-blue   { background: var(--blue-soft);  color: var(--blue); }
    .hbadge-green  { background: var(--green-soft);  color: var(--green); }
    .hbadge-amber  { background: var(--amber-soft);  color: var(--amber); }

    /* FORM FILTER */
    form {
        margin-left: 0;
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 0;
        background: var(--surface);
        padding: 12px 16px;
        border-radius: var(--r);
        border: 1px solid var(--line);
        box-shadow: var(--shadow);
        flex-wrap: wrap;
    }

    form label {
        font-size: 12px;
        font-weight: 600;
        color: var(--ink-muted);
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    /* SELECT */
    select {
        padding: 8px 12px;
        border-radius: 8px;
        border: 1.5px solid var(--line);
        background: var(--bg);
        cursor: pointer;
        font-size: 13.5px;
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
        outline: none;
        transition: .2s;
    }

    select:focus {
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }

    /* BUTTON GENERAL */
    button {
        padding: 8px 12px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.2s;
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
    }

    /* PRIMARY BUTTON */
    button:hover {
        opacity: 0.9;
        transform: translateY(-2px);
    }

    /* CREATE BUTTON */
    button[onclick="openModal()"] {
        margin-left: 0;
        margin-bottom: 0;
        background: var(--blue);
        color: white;
        font-weight: 500;
        padding: 9px 16px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        box-shadow: 0 4px 12px rgba(37,99,235,.25);
    }

    /* SEARCH INPUT */
    #search {
        margin-left: 0;
        padding: 9px 13px;
        width: 250px;
        border-radius: 8px;
        border: 1.5px solid var(--line);
        margin-bottom: 0;
        font-size: 13.5px;
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
        background: var(--bg);
        outline: none;
        transition: .2s;
    }

    #search:focus {
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        background: var(--surface);
    }

    /* ── Toolbar (search + create) ── */
    .toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* TABLE */
    table {
        padding: 15px;
        margin-left: 0;
        width: 100%;
        border-collapse: collapse;
        background: var(--surface);
        border-radius: var(--r);
        overflow: hidden;
        box-shadow: var(--shadow);
        border: 1px solid var(--line);
    }

    thead {
        background: var(--ink);
        color: white;
    }

    th, td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid var(--line);
        font-size: 13.5px;
    }

    th {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: rgba(255,255,255,.7);
        border-bottom: none;
    }

    tr:hover {
        background: #fafbfd;
        transition: .15s;
    }

    tbody tr:last-child td { border-bottom: none; }

    /* ── Avatar dans la table ── */
    .user-avatar-sm {
        width: 30px; height: 30px;
        border-radius: 50%;
        background: var(--blue);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 600;
        margin-right: 8px;
        vertical-align: middle;
        flex-shrink: 0;
    }

    .user-name-cell {
        display: flex;
        align-items: center;
        gap: 0;
    }

    /* ── Badges rôle / statut ── */
    .role-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px; border-radius: 20px;
        font-size: 11.5px; font-weight: 500;
    }

    .role-badge::before {
        content: ''; width: 5px; height: 5px;
        border-radius: 50%;
    }

    .role-admin { background: var(--blue-soft);  color: var(--blue); }
    .role-admin::before  { background: var(--blue); }
    .role-agent { background: rgba(139,92,246,.1); color: #7c3aed; }
    .role-agent::before  { background: #7c3aed; }

    .status-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px; border-radius: 20px;
        font-size: 11.5px; font-weight: 500;
    }

    .status-badge::before {
        content: ''; width: 5px; height: 5px; border-radius: 50%;
    }

    .status-actif   { background: var(--green-soft); color: var(--green); }
    .status-actif::before   { background: var(--green); }
    .status-inactif { background: var(--red-soft);   color: var(--red); }
    .status-inactif::before { background: var(--red); }

    /* ACTION BUTTONS */
    td button {
        margin-right: 5px;
        padding: 5px 10px;
        font-size: 12px;
        border-radius: 6px;
        font-weight: 500;
    }

    td button:nth-child(1) { background: var(--amber-soft); color: var(--amber); border: 1px solid rgba(217,119,6,.2); }
    td button:nth-child(2) { background: var(--red-soft);   color: var(--red);   border: 1px solid rgba(220,38,38,.2); }
    td button:nth-child(3) { background: var(--green-soft);  color: var(--green); border: 1px solid rgba(5,150,105,.2); }
    td button:nth-child(4) { background: var(--blue-soft);  color: var(--blue);  border: 1px solid rgba(37,99,235,.2); }

    /* LINKS */
    a {
        color: var(--blue);
        text-decoration: none;
        font-weight: 500;
    }

    a:hover { text-decoration: underline; }

    /* MODAL BACKGROUND */
    .modal {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(13,27,42,.5);
        backdrop-filter: blur(4px);
        z-index: 500;
        align-items: center;
        justify-content: center;
    }

    .modal[style*="block"] { display: flex !important; }

    /* MODAL BOX */
    .modal-content {
        background: var(--surface);
        width: 400px;
        margin: 0 auto;
        padding: 28px 28px 24px;
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(13,27,42,.2);
        border: 1px solid var(--line);
        animation: modalIn .25s cubic-bezier(.34,1.56,.64,1);
    }

    @keyframes modalIn {
        from { opacity: 0; transform: scale(.95) translateY(10px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .modal-content h3 {
        font-family: 'DM Serif Display', serif;
        font-size: 18px; font-weight: 400;
        color: var(--ink);
        margin-bottom: 20px;
    }

    .modal-divider {
        height: 1px;
        background: var(--line);
        margin-bottom: 18px;
    }

    .modal-field-label {
        display: block;
        font-size: 11.5px; font-weight: 600;
        color: var(--ink-muted);
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 6px;
    }

    .modal-field-group { margin-bottom: 14px; }

    /* MODAL INPUTS */
    .modal-content input {
        width: 100%;
        padding: 10px 13px;
        margin-bottom: 0;
        border-radius: 9px;
        border: 1.5px solid var(--line);
        font-size: 14px;
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
        background: var(--bg);
        outline: none;
        transition: .2s;
    }

    .modal-content input:focus {
        border-color: var(--blue);
        background: var(--surface);
        box-shadow: 0 0 0 4px rgba(37,99,235,.1);
    }

    .modal-footer {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    /* MODAL BUTTONS */
    .modal-content button:first-of-type {
        flex: 1;
        background: var(--blue);
        color: white;
        padding: 10px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(37,99,235,.25);
    }

    .modal-content button:last-of-type {
        flex: 1;
        background: var(--bg);
        color: var(--ink-muted);
        border: 1px solid var(--line);
        padding: 10px;
    }

    .modal-content button:last-of-type:hover { background: var(--line); }

    /* ── Toast succès création ── */
    .toast {
        position: fixed;
        bottom: 24px; right: 24px;
        background: var(--surface);
        border: 1px solid var(--line);
        border-left: 3px solid var(--green);
        border-radius: var(--r);
        padding: 14px 18px;
        box-shadow: 0 8px 24px rgba(13,27,42,.12);
        font-size: 13.5px;
        color: var(--ink);
        display: none;
        z-index: 600;
        max-width: 320px;
    }

    .toast strong { display: block; color: var(--green); margin-bottom: 4px; }

    </style>
</head>

<body>

    <?php
    $active_page = "users";
    include_once("nav.php");
    ?>

    <div class="main-wrapper">

        <!-- HEADER -->
        <header>
            <div>
                <div class="header-sub">Admin : <?= htmlspecialchars($admin_name) ?></div>
                <h1>Gestion utilisateurs</h1>
            </div>
            <div class="header-badges">
                <span class="hbadge hbadge-blue">
                    <i class="fa-solid fa-users" style="font-size:10px;"></i>
                    <?= $totalUsers ?> Uploadeurs
                </span>
                <span class="hbadge hbadge-amber">
                    <i class="fa-solid fa-shield-halved" style="font-size:10px;"></i>
                    <?= $totalAdmins ?> admins
                </span>
                <span class="hbadge hbadge-green">
                    <i class="fa-solid fa-circle" style="font-size:8px;"></i>
                    <?= $totalActifs ?> actifs
                </span>
            </div>
        </header>

        <!-- FORM FILTER -->
        <form method="GET">

            <label>Filtrer :</label>

            <select name="filter" onchange="this.form.submit()">
                <option value="all"      <?= $filter==='all'      ? 'selected':'' ?>>Tous</option>
                <option value="admin"    <?= $filter==='admin'    ? 'selected':'' ?>>Admins</option>
                <option value="agent"    <?= $filter==='agent'    ? 'selected':'' ?>>Agents</option>
                <option value="active"   <?= $filter==='active'   ? 'selected':'' ?>>Actifs</option>
                <option value="inactive" <?= $filter==='inactive' ? 'selected':'' ?>>Inactifs</option>
            </select>

            <label>Trier :</label>

            <select name="sort" onchange="this.form.submit()">
                <option value="az"  <?= $sort==='az'  ? 'selected':'' ?>>Nom A-Z</option>
                <option value="za"  <?= $sort==='za'  ? 'selected':'' ?>>Nom Z-A</option>
                <option value="new" <?= $sort==='new' ? 'selected':'' ?>>Plus récents</option>
                <option value="old" <?= $sort==='old' ? 'selected':'' ?>>Plus anciens</option>
            </select>

        </form>

        <!-- TOOLBAR : search + create -->
        <div class="toolbar">
            <button onclick="openModal()">
                <i class="fa-solid fa-user-plus"></i>
                Créer agent
            </button>
            <input type="text" id="search" placeholder="🔍  Rechercher un utilisateur...">
        </div>

        <!-- TABLE -->
        <table>

            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Activités</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody id="usersTable">

                <?php foreach ($users as $user): ?>
                <tr>

                    <td>
                        <div class="user-name-cell">
                            <div class="user-avatar-sm">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                            <?= htmlspecialchars($user['name']) ?>
                        </div>
                    </td>

                    <td style="color:var(--ink-muted);">
                        <?= htmlspecialchars($user['email']) ?>
                    </td>

                    <td>
                        <span class="role-badge role-<?= $user['role'] ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="status-badge <?= $user['is_active'] ? 'status-actif' : 'status-inactif' ?>">
                            <?= $user['is_active'] ? "Actif" : "Inactif" ?>
                        </span>
                    </td>

                    <td>
                        <a href="user_activity.php?user_id=<?= $user['id'] ?>">
                            <i class="fa-solid fa-chart-line" style="font-size:11px;"></i>
                            Voir activité
                        </a>
                    </td>

                    <td>
                        <?php if ($user['id'] != $admin_id): ?>
                            <button onclick="actionUser('reset_password',<?= $user['id'] ?>)" title="Réinitialiser mot de passe">
                                <i class="fa-solid fa-key"></i> Reset
                            </button>
                        <?php endif; ?>

                        <?php if ($user['id'] != $admin_id && !$user['is_system_admin']): ?>

                            <button onclick="actionUser('toggle_status',<?= $user['id'] ?>)" title="Activer / Désactiver">
                                <?= $user['is_active']
                                    ? '<i class="fa-solid fa-ban"></i> Désactiver'
                                    : '<i class="fa-solid fa-check"></i> Activer' ?>
                            </button>

                            <?php if ($user['role'] !== 'admin'): ?>
                                <button onclick="actionUser('promote_user',<?= $user['id'] ?>)" title="Promouvoir admin">
                                    <i class="fa-solid fa-arrow-up"></i> Promouvoir
                                </button>
                            <?php endif; ?>

                            <?php if ($user['role'] === 'admin'): ?>
                                <button onclick="actionUser('demote_admin',<?= $user['id'] ?>)" title="Rétrograder agent">
                                    <i class="fa-solid fa-arrow-down"></i> Rétrograder
                                </button>
                            <?php endif; ?>

                        <?php endif; ?>
                    </td>

                </tr>
                <?php endforeach; ?>

            </tbody>

        </table>

    </div><!-- /main-wrapper -->


    <!-- MODAL -->
    <div class="modal" id="createModal">
        <div class="modal-content">

            <h3><i class="fa-solid fa-user-plus" style="color:var(--blue);margin-right:8px;font-size:16px;"></i>Créer un agent</h3>
            <div class="modal-divider"></div>

            <div class="modal-field-group">
                <label class="modal-field-label">Nom complet</label>
                <input type="text" id="agentName" placeholder="Jean Dupont">
            </div>

            <div class="modal-field-group">
                <label class="modal-field-label">Adresse email</label>
                <input type="email" id="agentEmail" placeholder="agent@email.com">
            </div>

            <br><br>

            <div class="modal-footer">
                <button onclick="createAgent()">
                    <i class="fa-solid fa-check"></i> Créer
                </button>
                <button onclick="closeModal()">
                    <i class="fa-solid fa-xmark"></i> Fermer
                </button>
            </div>

        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>


    <script>

        function actionUser(action, userId) {
            fetch("users.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=" + action + "&user_id=" + userId
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function createAgent() {
            let name  = document.getElementById("agentName").value;
            let email = document.getElementById("agentEmail").value;

            fetch("users.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=create_agent&name=" + name + "&email=" + email
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    closeModal();
                    /* ✅ Afficher le mot de passe temporaire dans un toast */
                    const toast = document.getElementById("toast");
                    toast.innerHTML = `
                        <strong>✅ Agent créé avec succès !</strong>
                        Nom : ${data.agentName}<br>
                        Mot de passe temporaire : <strong>${data.tempPassword}</strong><br>
                        <small style="color:#7f8fa4;">Notez ce mot de passe, il ne sera plus affiché.</small>
                    `;
                    toast.style.display = "block";
                    setTimeout(() => { location.reload(); }, 6000);
                } else {
                    alert(data.message);
                }
            });
        }

        function openModal() {
            document.getElementById("createModal").style.display = "block";
        }

        function closeModal() {
            document.getElementById("createModal").style.display = "none";
        }

        const searchInput = document.getElementById("search");
        searchInput.addEventListener("keyup", function() {
            let filter = searchInput.value.toLowerCase();
            let rows = document.querySelectorAll("#usersTable tr");
            rows.forEach(row => {
                let name  = row.children[0].textContent.toLowerCase();
                let email = row.children[1].textContent.toLowerCase();
                row.style.display = (name.includes(filter) || email.includes(filter)) ? "" : "none";
            });
        });

    </script>

</body>
</html>