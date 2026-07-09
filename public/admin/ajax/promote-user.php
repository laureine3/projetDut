<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";

requireRole('admin');

/* =============================
   Récupération utilisateurs
============================= */

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Compteur admins */
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'");
$adminCount = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Gestion des utilisateurs</title>

<style>
body {
    margin:0;
    font-family: Arial;
    background:#0f172a;
    color:#e2e8f0;
}

.container {
    padding:40px;
}

h1 {
    color:#1e3a8a;
}

.admin-count {
    margin-bottom:20px;
    font-size:14px;
    color:#94a3b8;
}

table {
    width:100%;
    border-collapse:collapse;
    background:#1e293b;
    border-radius:8px;
    overflow:hidden;
}

th, td {
    padding:12px;
    text-align:left;
}

th {
    background:#1e3a8a;
}

tr:nth-child(even) {
    background:#243044;
}

button {
    padding:6px 10px;
    border:none;
    cursor:pointer;
    border-radius:4px;
    font-size:13px;
    margin:2px;
}

.btn-create { background:#1e3a8a; color:white; }
.btn-reset { background:#0ea5e9; color:white; }
.btn-deactivate { background:#dc2626; color:white; }
.btn-reactivate { background:#16a34a; color:white; }
.btn-promote { background:#facc15; color:black; }

.badge-admin { color:#facc15; font-weight:bold; }
.badge-agent { color:#38bdf8; font-weight:bold; }
.badge-inactive { color:#ef4444; }

.modal {
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.7);
    justify-content:center;
    align-items:center;
}

.modal-content {
    background:#1e293b;
    padding:30px;
    border-radius:8px;
    width:400px;
}

input, select {
    width:100%;
    padding:8px;
    margin-bottom:15px;
    border:none;
    border-radius:4px;
}
</style>
</head>

<body>

<div class="container">

    <h1>Gestion des utilisateurs système</h1>

    <div class="admin-count">
        Nombre d'admins : <?= $adminCount ?> / 3
    </div>

    <button class="btn-create" onclick="openModal()">Créer un utilisateur</button>

    <table>
        <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php foreach($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td>
                <?php if($user['role'] === 'admin'): ?>
                    <span class="badge-admin">ADMIN</span>
                <?php else: ?>
                    <span class="badge-agent">AGENT</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if($user['is_active']): ?>
                    Actif
                <?php else: ?>
                    <span class="badge-inactive">Désactivé</span>
                <?php endif; ?>
            </td>
            <td>

                <?php if($user['id'] != $_SESSION['user_id']): ?>

                    <?php if($user['is_active']): ?>

                        <button class="btn-reset" onclick="resetPassword(<?= $user['id'] ?>)">Reset</button>

                        <button class="btn-deactivate" onclick="deactivateUser(<?= $user['id'] ?>)">Désactiver</button>

                        <?php if($user['role'] === 'agent' && $adminCount < 3): ?>
                            <button class="btn-promote" onclick="promoteUser(<?= $user['id'] ?>)">
                                Promouvoir Admin
                            </button>
                        <?php endif; ?>

                    <?php else: ?>

                        <button class="btn-reactivate" onclick="reactivateUser(<?= $user['id'] ?>)">Réactiver</button>

                    <?php endif; ?>

                <?php endif; ?>

            </td>
        </tr>
        <?php endforeach; ?>
    </table>

</div>

<!-- Modal création -->
<div class="modal" id="createModal">
    <div class="modal-content">
        <h3>Créer utilisateur</h3>
        <input type="text" id="name" placeholder="Nom">
        <input type="email" id="email" placeholder="Email">
        <select id="role">
            <option value="agent">Agent</option>
            <?php if($adminCount < 3): ?>
                <option value="admin">Admin</option>
            <?php endif; ?>
        </select>
        <button onclick="createUser()">Créer</button>
        <button onclick="closeModal()">Annuler</button>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('createModal').style.display = "flex";
}

function closeModal() {
    document.getElementById('createModal').style.display = "none";
}

function createUser() {
    let formData = new FormData();
    formData.append("name", document.getElementById("name").value);
    formData.append("email", document.getElementById("email").value);
    formData.append("role", document.getElementById("role").value);

    fetch("ajax/create-user.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === "success"){
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function resetPassword(id) {
    if(!confirm("Confirmer la réinitialisation ?")) return;

    let formData = new FormData();
    formData.append("user_id", id);

    fetch("ajax/reset-password.php", {
        method:"POST",
        body:formData
    })
    .then(res=>res.json())
    .then(data=>{
        alert(data.message);
    });
}

function deactivateUser(id) {
    if(!confirm("Confirmer la désactivation ?")) return;

    let formData = new FormData();
    formData.append("user_id", id);

    fetch("ajax/delete-user.php", {
        method:"POST",
        body:formData
    })
    .then(res=>res.json())
    .then(data=>{
        alert(data.message);
        if(data.status==="success") location.reload();
    });
}

function reactivateUser(id) {
    let formData = new FormData();
    formData.append("user_id", id);

    fetch("ajax/reactivate-user.php", {
        method:"POST",
        body:formData
    })
    .then(res=>res.json())
    .then(data=>{
        alert(data.message);
        if(data.status==="success") location.reload();
    });
}

function promoteUser(id) {
    if(!confirm("Promouvoir cet agent en admin ?")) return;

    let formData = new FormData();
    formData.append("user_id", id);

    fetch("ajax/promote-user.php", {
        method:"POST",
        body:formData
    })
    .then(res=>res.json())
    .then(data=>{
        alert(data.message);
        if(data.status==="success") location.reload();
    });
}
</script>

</body>
</html>