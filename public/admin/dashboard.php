<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";

requireRole('admin');

$stmt = $pdo->prepare("SELECT name, profile_image FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profileImage = !empty($user['profile_image'])
    /*? "../../uploads/profiles/" . $user['profile_image']
    : "https://via.placeholder.com/50"*/;

/* Notifications initial count */
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE (user_id = ? OR user_id IS NULL)
    AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
$unreadCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

/* ── Variables ── */
:root {
    --ink:        #0d1b2a;
    --ink-muted:  #7f8fa4;
    --line:       #e8ecf1;
    --surface:    #ffffff;
    --bg:         #f5f7fa;
    --blue:       #2563eb;
    --blue-soft:  #eff4ff;
    --green:      #059669;
    --green-soft: #ecfdf5;
    --amber:      #d97706;
    --amber-soft: #fffbeb;
    --red:        #dc2626;
    --red-soft:   #fef2f2;
    --shadow:     0 4px 16px rgba(13,27,42,.08);
    --r:          10px;

    /* Gardés pour compatibilité avec l'ancien code */
    --text-main:  #0d1b2a;
    --text-muted: #7f8fa4;
    --primary:    #2563eb;
    --white:      #ffffff;
}

/* ── Base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    margin: 0;
    font-family: 'DM Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: var(--bg);
    color: var(--ink);
    display: flex;
}

/* ── Main wrapper ── */
.main {
    margin-left: 220px;
    padding: 28px;
    flex: 1;
    min-height: 100vh;
}

/*HEADER*/
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--r);
    padding: 16px 22px;
    box-shadow: var(--shadow);
}

.header-left h1 {
    margin: 0;
    font-family: 'DM Serif Display', serif;
    font-size: 1.5rem;
    font-weight: 400;
    color: var(--ink);
}

.header-left .header-sub {
    font-size: 12px;
    color: var(--ink-muted);
    margin-top: 2px;
}

/* ── Horloge ── */
.clock-box {
    font-family: 'DM Serif Display', serif;
    font-size: 1.4rem;
    color: var(--ink);
    font-weight: 400;
    background: var(--bg);
    border: 1px solid var(--line);
    padding: 8px 18px;
    border-radius: 8px;
    letter-spacing: 2px;
}

/* ── Profile box ── */
.profile-box {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

.profile-box img {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--blue);
}

/* Avatar fallback (si pas d'image) */
.profile-avatar-fallback {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--blue);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.profile-name {
    font-weight: 600;
    font-size: 13.5px;
    color: var(--ink);
}

.profile-role-label {
    font-size: 11px;
    color: var(--ink-muted);
}

/* ── Notification button ── */
.notification-btn {
    position: relative;
    cursor: pointer;
    width: 36px;
    height: 36px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ink-muted);
    font-size: 16px;
    transition: .2s;
}

.notification-btn:hover {
    border-color: var(--blue);
    color: var(--blue);
}

.badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: var(--red);
    color: white;
    font-size: 9px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    border: 1.5px solid var(--surface);
}

/* ── Notification box ── */
.notification-box {
    position: absolute;
    top: 52px;
    right: 0;
    width: 300px;
    background-color: var(--surface);
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: 0 8px 30px rgba(13,27,42,.13);
    display: none;
    z-index: 1000;
    overflow: hidden;
    animation: fadeDown .2s ease;
}

@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.notification-box h4 {
    margin: 0;
    padding: 13px 16px;
    border-bottom: 1px solid var(--line);
    font-size: 13.5px;
    font-weight: 600;
    color: var(--ink);
}

.notification-item {
    padding: 11px 16px;
    border-bottom: 1px solid var(--line);
    font-size: 13px;
    color: var(--ink);
    transition: .15s;
}

.notification-item:hover { background: var(--bg); }
.notification-item:last-child { border-bottom: none; }
.notification-item strong { display: block; font-size: 13px; }
.notification-item small { font-size: 11px; color: var(--ink-muted); }

/* ── Upload button ── */
.upload {
    border-radius: 8px;
    padding: 8px 16px;
    background-color: var(--blue-soft);
    border: 1.5px solid #bdd0ff;
    cursor: pointer;
    transition: .2s;
}

.upload:hover { background-color: #e0eaff; }

.btn {
    text-decoration: none;
    color: var(--blue);
    font-weight: 600;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/*CARDS*/
.card {
    background-color: var(--surface);
    padding: 22px 24px;
    border-radius: var(--r);
    box-shadow: var(--shadow);
    margin-bottom: 20px;
    border: 1px solid var(--line);
}

.card h3 {
    margin-top: 0;
    margin-bottom: 18px;
    font-size: 14px;
    font-weight: 600;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}

.card h3::before {
    content: '';
    width: 3px;
    height: 16px;
    background: var(--blue);
    border-radius: 2px;
    flex-shrink: 0;
}

/* ══════════════════════════════
   STATS GRID
══════════════════════════════ */
.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.stat-box1,
.stat-box2,
.stat-box3 {
    background-color: var(--bg);
    padding: 20px;
    border-radius: 9px;
    border: 1px solid var(--line);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    border-left: 4px solid;
}

.stat-box1 { border-left-color: var(--red); }
.stat-box2 { border-left-color: var(--amber); }
.stat-box3 { border-left-color: var(--green); }

.stat-box1:hover,
.stat-box2:hover,
.stat-box3:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(13,27,42,.08);
}

.stat-box1 h4,
.stat-box2 h4,
.stat-box3 h4 {
    margin-top: 0;
    margin-bottom: 14px;
    color: var(--ink-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.stat-box1 p,
.stat-box2 p,
.stat-box3 p {
    margin: 8px 0;
    font-size: 13.5px;
    color: var(--ink);
    justify-content: space-between;
    align-items: center;
    display: flex;
}

.stat-box1 span,
.stat-box2 span,
.stat-box3 span {
    font-family: 'DM Serif Display', serif;
    font-size: 1.3rem;
    font-weight: 400;
    color: var(--ink);
}

/* Alert admin seul */
.alert {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 9px 12px;
    background: var(--amber-soft);
    border: 1px solid #fde68a;
    border-radius: 7px;
    font-size: 12.5px;
    color: var(--amber);
    font-weight: 500;
}

/* ACTIVITÉ RÉCENTE */
#recentLogs p {
    padding: 10px 0;
    border-bottom: 1px solid var(--line);
    font-size: 13.5px;
    color: var(--ink);
    margin: 0;
}

#recentLogs p strong {
    color: var(--blue);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .3px;
}

#recentLogs p small {
    display: block;
    font-size: 11px;
    color: var(--ink-muted);
    margin-top: 2px;
}

#recentLogs p:last-child { border-bottom: none; }

/*DOCUMENTS RÉCENTS */
.recent-docs {
    background-color: var(--surface);
    padding: 22px 24px;
    border-radius: var(--r);
    box-shadow: var(--shadow);
    margin-bottom: 20px;
    border: 1px solid var(--line);
}

.recent-docs h3 {
    margin-top: 0;
    margin-bottom: 18px;
    font-size: 14px;
    font-weight: 600;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}

.recent-docs h3::before {
    content: '';
    width: 3px;
    height: 16px;
    background: var(--blue);
    border-radius: 2px;
    flex-shrink: 0;
}

/* ── Table ── */
table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    text-align: left;
    border-bottom: 1px solid var(--line);
}

thead th {
    color: var(--ink-muted);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 10px 12px;
    background: var(--bg);
}

th, td {
    padding: 11px 12px;
}

tbody tr {
    border-top: 1px solid var(--line);
    transition: .15s;
}

tbody tr:hover {
    background: var(--bg);
}

td {
    font-size: 13.5px;
    color: var(--ink);
}

/* ── Statuts ── */
.status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 500;
}

.status::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
}

.valid {
    background: var(--green-soft);
    color: var(--green);
}
.valid::before { background: var(--green); }

.pending {
    background: var(--amber-soft);
    color: var(--amber);
}
.pending::before { background: var(--amber); }

.rejected {
    background: var(--red-soft);
    color: var(--red);
}
.rejected::before { background: var(--red); }

/* ── Voir tout ── */
.view-all {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 14px;
    color: var(--blue);
    text-decoration: none;
    font-weight: 500;
    font-size: 13px;
    transition: .2s;
}

.view-all:hover { text-decoration: underline; }

/* ── Profile img ── */
.profile-img {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--line);
}

</style>
</head>

<body>

<?php
    $active_page = "dashboard";
    include_once("nav.php");
?>

<div class="main">

<div class="header">
<div class="header-left">
    <h1>Tableau de bord</h1>
    <div class="header-sub" id="header-date"></div>
</div>
<div class="clock-box" id="clock">00:00</div>

<div class="profile-box">
<div class="notification-btn" onclick="toggleNotifications()">
    <i class="fa-solid fa-bell"></i>
<span class="badge" id="notifBadge" style="<?= $unreadCount > 0 ? '' : 'display:none;' ?>">
<?= $unreadCount ?>
</span>
</div>

<div class="notification-box" id="notificationBox">
<h4>Notifications</h4>
<div id="notificationList"></div>
</div>

<img src="<?= $profileImage ?>">
<div>
    <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
    <div class="profile-role-label">Administrateur</div>
</div>
</div>
<div class="upload">
   <!-- <a href="/projetDUT/public/user/upload_document.php" class="btn">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        Nouveau document
    </a>-->
     <a href="/projetDUT/index.html" class="btn">
        <i class="fas fa-arrow-left"></i>
        Retour
    </a>
</div>
</div>

<div class="card">
<h3>Statistiques générales</h3>

<div class="stats">

<div class="stat-box1">
<h4>Utilisateurs</h4>
<p>Total : <span id="totalUsers">0</span></p>
<p>Admins actifs : <span id="totalAdmins">0</span></p>
<p>Agents actifs : <span id="totalAgents">0</span></p>
<div id="adminAlert"></div>
</div>

<div class="stat-box2">
<h4>Documents</h4>
<p>Total : <span id="totalDocuments">0</span></p>
<p>En attente : <span id="pendingDocuments">0</span></p>
</div>

<div class="stat-box3">
<h4>Validation</h4>
<p style="color: #08e458;">Validés : <span id="validatedDocuments">0</span></p>
<p style="color: #fa0909;">Rejetés : <span id="rejectedDocuments">0</span></p>

</div>

</div>
</div>

<div class="card">
<h3>Activité récente</h3>
<div id="recentLogs"></div>
</div>

<div class="recent-docs">
<h3>Documents récents</h3>

<table>
    <thead>
        <tr>
            <th>Nom</th>
            <th>Catégorie</th>
            <th>Ajouté le</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
        <tr>
            <td>Contrat_2024.pdf</td>
            <td>Contrats</td>
            <td>24/05/2025</td>
            <td> <span class="status valid"> Validé</span></td>
            <td>:</td>
        </tr>
         <tr>
            <td>Rappoort_activité.pdf</td>
            <td>Rapports</td>
            <td>22/05/2025</td>
            <td> <span class="status pending">En attente</span></td>
            <td>:</td>
        </tr>
         <tr>
            <td>Facture_Mai.pdf</td>
            <td>Factures</td>
            <td>20/05/2025</td>
            <td> <span class="status valid">Validé</span></td>
            <td>:</td>
        </tr>
         <tr>
            <td>Devis_projet.pdf</td>
            <td>Factures</td>
            <td>15/05/2025</td>
            <td><span class="status rejected">refusé</span></td>
            <td>:</td>
        </tr>
    </tbody>
</table>

<a href="documents.php" class="view-all">Voir tout →</a>
</div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function(){

/* HORLOGE */
const DAYS   = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const MONTHS = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

function updateClock(){
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById("clock").textContent = h + " : " + m + " : " + s;
    document.getElementById("header-date").textContent =
        DAYS[now.getDay()] + ' ' + now.getDate() + ' ' + MONTHS[now.getMonth()] + ' ' + now.getFullYear();
}
setInterval(updateClock,1000);
updateClock();

/* NOTIFICATIONS */
window.toggleNotifications=function(){
const box=document.getElementById("notificationBox");
box.style.display=box.style.display==="block"?"none":"block";
if(box.style.display==="block"){markRead();}
};

function fetchNotifications(){
fetch('/projetDUT/api/notifications/fetch.php')
.then(res=>res.json())
.then(data=>{
const badge=document.getElementById("notifBadge");
const list=document.getElementById("notificationList");

badge.innerText=data.length;
badge.style.display=data.length>0?"inline-block":"none";

if(data.length===0){
list.innerHTML="<p style='padding:16px;text-align:center;color:#94a3b8;font-size:13px;'>Aucune notification</p>";
return;
}

let html="";
data.forEach(n=>{
html+=`<div class="notification-item unread">
<strong>${n.title}</strong><br>
${n.message}<br>
<small>${n.created_at}</small>
</div>`;
});
list.innerHTML=html;
});
}

function markRead(){
fetch('/projetDUT/api/notifications/mark-read.php',{method:'POST'})
.then(()=>{document.getElementById("notifBadge").style.display="none";});
}
setInterval(fetchNotifications,5000);
fetchNotifications();

/* Fermer notif en cliquant ailleurs */
document.addEventListener("click", function(e){
    const box = document.getElementById("notificationBox");
    if (!e.target.closest('.notification-box') && !e.target.closest('.notification-btn')) {
        box.style.display = "none";
    }
});

/* STATS */
function fetchStats(){
fetch('/projetDUT/api/stats/fetch.php')
.then(res=>res.json())
.then(d=>{
document.getElementById("totalUsers").textContent=d.totalUsers;
document.getElementById("totalAdmins").textContent=d.totalAdmins;
document.getElementById("totalAgents").textContent=d.totalAgents;
document.getElementById("totalDocuments").textContent=d.totalDocuments;
document.getElementById("pendingDocuments").textContent=d.pendingDocuments;
document.getElementById("validatedDocuments").textContent=d.validatedDocuments;
document.getElementById("rejectedDocuments").textContent=d.rejectedDocuments;

if(d.totalAdmins<=1){
document.getElementById("adminAlert").innerHTML=
'<div class="alert"><i class="fa-solid fa-triangle-exclamation"></i> Un seul admin actif !</div>';
}else{
document.getElementById("adminAlert").innerHTML="";
}
});
}
setInterval(fetchStats,5000);
fetchStats();

/* LOGS */
function fetchLogs(){
fetch('/projetDUT/api/logs/fetch.php')
.then(res=>res.json())
.then(data=>{
const container=document.getElementById("recentLogs");
if(data.length===0){
container.innerHTML="<p style='padding:16px;text-align:center;color:#94a3b8;font-size:13px;'>Aucune activité récente.</p>";
return;
}
let html="";
data.forEach(log=>{
html+=`<p>
<strong>${log.action_type}</strong> —
${log.description}<br>
<small>${log.logged_at}</small>
</p>`;
});
container.innerHTML=html;
});
}
setInterval(fetchLogs,5000);
fetchLogs();

});
</script>

</body>
</html>
