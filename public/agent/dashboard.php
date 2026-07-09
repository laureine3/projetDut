<?php
session_start();

#require_once "../../config/auth.php";
#requireRole('agent'); // 🔥 utilise ton système sécurisé
#require_once "../../config/database.php";

require_once "../../config/auth.php";
requireRole('agent');
/* ======================
   STATISTIQUES
====================== */

$totalDocs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

$validatedDocs = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='validated'")
    ->fetchColumn();

$rejectedDocs = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='rejected'")
    ->fetchColumn();

// /* ======================
//    NOTIFICATIONS RECENTES
// ====================== */

// $notifStmt = $pdo->prepare("
//     SELECT n.*, u.name as sender_name, u.role as sender_role
//     FROM notifications n
//     LEFT JOIN users u ON n.sender_id = u.id
//     ORDER BY n.created_at DESC
//     LIMIT 5
// ");
// $notifStmt->execute();
// $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================
   DOCUMENTS PENDING
====================== */

$pendingStmt = $pdo->prepare("
    SELECT d.id, d.file_name, u.name as user_name
    FROM documents d
    JOIN users u ON d.user_id = u.id
    WHERE d.status='pending'
    ORDER BY d.uploaded_at DESC
    LIMIT 5
");
$pendingStmt->execute();
$pendingDocs = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Agent</title>
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
    --red:        #dc2626;
    --red-soft:   #fef2f2;
    --amber:      #d97706;
    --shadow:     0 4px 16px rgba(13,27,42,.08);
    --r:          10px;
    --text-main:  #0d1b2a;
}

/* RESET */
*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'DM Sans', 'Segoe UI', sans-serif;
}

/* BODY */
body {
    margin: 0;
    font-family: 'DM Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: var(--bg);
    color: var(--text-main);
    display: flex;
}

/* MAIN */
main {
    margin-left: 220px;
    padding: 28px;
    flex: 1;
    min-height: 100vh;
}

/* HEADER */
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--surface);
    padding: 16px 22px;
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
}

/* TITLE */
header h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 20px;
    font-weight: 400;
    color: var(--ink);
    text-transform: capitalize;
}

header h1 span {
    color: var(--blue);
}

/* QUICK ACTIONS */
#quick_actions {
    display: flex;
    gap: 8px;
}

#quick_actions i {
    width: 36px;
    height: 36px;
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ink-muted);
    font-size: 14px;
    font-style: normal;
}

#quick_actions i:hover {
    background: var(--blue);
    color: white;
    border-color: var(--blue);
    transform: translateY(-2px);
}

/* TOP DISPLAY */
.top-display {
    background: var(--surface);
    padding: 22px 24px;
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
}

.top-display h3 {
    margin-bottom: 18px;
    font-size: 14px;
    font-weight: 600;
    color: var(--ink);
    text-transform: capitalize;
    display: flex;
    align-items: center;
    gap: 8px;
}

.top-display h3::before {
    content: '';
    width: 3px;
    height: 16px;
    background: var(--blue);
    border-radius: 2px;
    flex-shrink: 0;
}

/* STATS GRID */
.stats-inner {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}

/* STATS BOXES */
#total,
#validated,
#rejected {
    background: var(--bg);
    padding: 18px 16px;
    border-radius: 9px;
    border: 1px solid var(--line);
    text-align: center;
    font-weight: 500;
    transition: 0.25s;
    border-left: 4px solid;
}

#total span,
#validated span,
#rejected span {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    font-weight: 400;
    display: block;
    color: var(--ink);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--ink-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* COLORS */
#total     { border-left-color: var(--blue); }
#validated { border-left-color: var(--green); }
#rejected  { border-left-color: var(--red); }

/* HOVER */
#total:hover,
#validated:hover,
#rejected:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(13,27,42,.08);
}

/* CLOCK */
#clock {
    font-family: 'DM Serif Display', serif;
    font-size: 1.6rem;
    font-weight: 400;
    color: var(--ink);
    text-align: center;
    background: var(--ink);
    color: white;
    border-radius: 9px;
    padding: 18px 16px;
    letter-spacing: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* BOTTOM DISPLAY */
.bottom-display {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--r);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 24px;
}

/* TABLE */
#pending-documents,
#recent-notifications {
    width: 100%;
    border-collapse: collapse;
}

#pending-documents thead,
#recent-notifications thead {
    display: block;
    padding: 14px 18px;
    border-bottom: 1px solid var(--line);
    font-size: 14px;
    font-weight: 600;
    color: var(--ink);
}

#pending-documents th,
#recent-notifications th {
    padding: 10px 16px;
    font-size: 11px;
    font-weight: 600;
    color: var(--ink-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    text-align: left;
    background: var(--bg);
    border-bottom: 1px solid var(--line);
}

#pending-documents td,
#recent-notifications td {
    padding: 12px 16px;
    font-size: 13.5px;
    color: var(--ink);
    border-bottom: 1px solid var(--line);
}

#pending-documents tr:last-child td,
#recent-notifications tr:last-child td {
    border-bottom: none;
}

#pending-documents tbody tr:hover,
#recent-notifications tbody tr:hover {
    background: var(--bg);
    transition: .15s;
}

/* Liens dans la table */
#pending-documents a,
#recent-notifications a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--blue);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 6px;
    background: var(--blue-soft);
    transition: .2s;
}

#pending-documents a:hover,
#recent-notifications a:hover {
    background: #dbeafe;
}

/* Lien commencer */
a[href="documents.php?filter=pending"] {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--blue);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(37,99,235,.25);
    transition: .2s;
}

a[href="documents.php?filter=pending"]:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

</style>
</head>
<body>

<nav>
<?php include_once("nav.php"); ?>
</nav>

<main>
  <header>
<h1>bienvenue, agent <span><?= htmlspecialchars($_SESSION['name']) ?></span></h1>
<div id="quick_actions">
<i class="fa-solid fa-bell" title="Notifications"></i>
<i class="fa-solid fa-gear" title="Paramètres"></i>
</div>
</header>

  <div class="top-display">
    <h3>statistiques documentaire</h3>

    <div class="stats-inner">
        <div id="total">
            <span><?= $totalDocs ?></span>
            <div class="stat-label">Documents traités</div>
        </div>
        <div id="validated">
            <span><?= $validatedDocs ?></span>
            <div class="stat-label">Validés</div>
        </div>
        <div id="rejected">
            <span><?= $rejectedDocs ?></span>
            <div class="stat-label">Rejetés</div>
        </div>
        <div id="clock"></div>
    </div>
  </div>

<div class="bottom-display">

<!-- <table id="recent-notifications">
<thead>notifications recents</thead>
<tr>
<th>Sources</th>
<th>Titre</th>
<th>Message</th>
</tr>

<?php foreach($notifications as $notif): ?>
<tr>
<td><?= htmlspecialchars($notif['sender_role'] . " - " . $notif['sender_name']) ?></td>
<td><?= htmlspecialchars($notif['title']) ?></td>
<td><?= htmlspecialchars($notif['message']) ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td><a href="notifications.php">Voir tout</a></td>
</tr>
</table> -->

<table id="pending-documents">
<thead>Documents en attente</thead>
<tr>
<th>Utilisateur</th>
<th>Nom</th>
<th>Actions</th>
</tr>

<?php foreach($pendingDocs as $doc): ?>
<tr>
<td><?= htmlspecialchars($doc['user_name']) ?></td>
<td><?= htmlspecialchars($doc['file_name']) ?></td>
<td><a href="view-document.php?id=<?= $doc['id'] ?>">
    <i class="fa-solid fa-eye"></i> Voir
</a></td>
</tr>
<?php endforeach; ?>

<tr>
<td colspan="3"><a href="documents.php?filter=pending">Voir tout →</a></td>
</tr>
</table>

</div>
<a href="documents.php">
    <i class="fa-solid fa-play"></i>
    Documents
</a>
</main>

<script>
function updateClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    document.getElementById("clock").textContent =
        hours + " : " + minutes + " : " + seconds;
}
setInterval(updateClock, 1000);
updateClock();
</script>

</body>
</html>