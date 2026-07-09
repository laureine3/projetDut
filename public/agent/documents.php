<?php
require_once "../../config/auth.php";
requireRole('agent');
require_once "../../config/database.php";

/* ==========================
   PARAMÈTRES GET
========================== */

$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$tri    = $_GET['tri'] ?? 'a-z';

/* ==========================
   PAGINATION
========================== */
$perPage = 10;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* ==========================
   CONSTRUCTION REQUÊTE
========================== */

$sql = "
SELECT d.id, d.file_name, d.status, d.uploaded_at, u.name as user_name
FROM documents d
JOIN users u ON d.user_id = u.id
WHERE 1=1
";

$params = [];

/* ----- Filtre statut ----- */
if ($filter !== 'all') {
    $sql .= " AND d.status = ?";
    $params[] = $filter;
}

/* ----- Recherche ----- */
if (!empty($search)) {
    $sql .= " AND (d.file_name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

/* ----- Tri ----- */
switch ($tri) {
    case 'z-a':
        $sql .= " ORDER BY d.file_name DESC";
        break;
    case 'newer':
        $sql .= " ORDER BY d.uploaded_at DESC";
        break;
    case 'older':
        $sql .= " ORDER BY d.uploaded_at ASC";
        break;
    default:
        $sql .= " ORDER BY d.file_name ASC";
}

/* ----- Total pour pagination ----- */
$sqlCount = "SELECT COUNT(*) FROM documents d JOIN users u ON d.user_id = u.id WHERE 1=1";
$paramsCount = [];
if ($filter !== 'all') { $sqlCount .= " AND d.status = ?"; $paramsCount[] = $filter; }
if (!empty($search))   { $sqlCount .= " AND (d.file_name LIKE ? OR u.name LIKE ?)"; $paramsCount[] = "%$search%"; $paramsCount[] = "%$search%"; }
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($paramsCount);
$totalDocs = $stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalDocs / $perPage));

/* ----- Requête paginée ----- */
$sql .= " LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================
   COMPTEURS PAR STATUT
========================== */
$counts = $pdo->query("
    SELECT status, COUNT(*) as total
    FROM documents
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$totalAll       = array_sum($counts);
$totalPending   = $counts['pending']   ?? 0;
$totalValidated = $counts['validated'] ?? 0;
$totalRejected  = $counts['rejected']  ?? 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion Documents</title>
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
    display: flex;
    min-height: 100vh;
}

/* MAIN */
main {
    margin-left: 220px;
    padding: 28px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* HEADER */
header {
    background: var(--surface);
    padding: 16px 22px;
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
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

header p {
    font-size: 13px;
    color: var(--ink-muted);
    margin-top: 2px;
}

/* ── Badges compteurs ── */
.header-badges {
    display: flex;
    gap: 8px;
}

.hbadge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.hbadge-pending   { background: var(--amber-soft); color: var(--amber); }
.hbadge-validated { background: var(--green-soft);  color: var(--green); }
.hbadge-rejected  { background: var(--red-soft);    color: var(--red); }

/* WIDGETS (FILTRES) */
#widgets form {
    background: var(--surface);
    padding: 13px 16px;
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

/* INPUT + SELECT */
input[type="text"],
select {
    padding: 9px 12px;
    border-radius: 8px;
    border: 1.5px solid var(--line);
    outline: none;
    transition: .2s;
    min-width: 150px;
    font-size: 13.5px;
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
    background: var(--bg);
}

/* FOCUS EFFECT */
input:focus,
select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    background: var(--surface);
}

/* BUTTON */
button {
    background: var(--blue);
    color: white;
    border: none;
    padding: 9px 18px;
    border-radius: 8px;
    cursor: pointer;
    transition: .2s;
    font-size: 13.5px;
    font-family: 'DM Sans', sans-serif;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

button:hover {
    background: var(--blue-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37,99,235,.25);
}

/* TABLE CONTAINER */
.table-container {
    background: var(--surface);
    border-radius: var(--r);
    overflow: hidden;
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
}

/* TABLE */
table {
    width: 100%;
    border-collapse: collapse;
}

/* HEADER TABLE */
thead {
    background: var(--bg);
    color: var(--ink-muted);
    border-bottom: 1px solid var(--line);
}

th {
    padding: 10px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
}

td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--line);
    font-size: 13.5px;
    color: var(--ink);
}

tbody tr:last-child td { border-bottom: none; }

/* ROW HOVER */
tbody tr:hover { background: #fafbfd; transition: .15s; }

/* STATUS BADGES */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge::before {
    content: '';
    width: 5px; height: 5px;
    border-radius: 50%;
}

.badge-validated { background: var(--green-soft); color: var(--green); }
.badge-validated::before { background: var(--green); }
.badge-rejected  { background: var(--red-soft);   color: var(--red); }
.badge-rejected::before  { background: var(--red); }
.badge-pending   { background: var(--amber-soft); color: var(--amber); }
.badge-pending::before   { background: var(--amber); }
.badge-new       { background: var(--blue-soft);  color: var(--blue); }
.badge-new::before       { background: var(--blue); }

/* LINKS */
a {
    color: var(--blue);
    text-decoration: none;
    font-weight: 500;
}

a:hover { text-decoration: underline; }

/* Bouton Traiter / Voir */
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 500;
    text-decoration: none;
    transition: .2s;
}

.btn-treat {
    background: var(--blue);
    color: white;
    box-shadow: 0 2px 8px rgba(37,99,235,.2);
}

.btn-treat:hover {
    background: var(--blue-dark);
    text-decoration: none;
    color: white;
}

.btn-view {
    background: var(--bg);
    color: var(--ink-muted);
    border: 1px solid var(--line);
}

.btn-view:hover {
    background: var(--line);
    text-decoration: none;
    color: var(--ink);
}

/* Icône fichier */
.file-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.file-cell i {
    color: var(--ink-muted);
    font-size: 14px;
    flex-shrink: 0;
}

/* Vide */
.empty-row td {
    text-align: center;
    padding: 40px;
    color: var(--ink-muted);
    font-size: 14px;
}

.empty-row i {
    display: block;
    font-size: 32px;
    margin-bottom: 10px;
    color: var(--line);
}

/* ── PAGINATION ── */
.pagination-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-top: 1px solid var(--line);
    font-size: 13px;
    color: var(--ink-muted);
}

.pagination {
    display: flex;
    gap: 5px;
}

.page-btn {
    width: 30px; height: 30px;
    border: 1px solid var(--line);
    border-radius: 7px;
    background: var(--surface);
    cursor: pointer;
    font-size: 13px;
    display: flex; align-items: center; justify-content: center;
    transition: .2s;
    color: var(--ink-muted);
    text-decoration: none;
}

.page-btn:hover { border-color: var(--blue); color: var(--blue); text-decoration: none; }
.page-btn.active { background: var(--blue); color: white; border-color: var(--blue); }
.page-btn.disabled { opacity: .4; pointer-events: none; }

</style>
</head>
<body>

<nav>
<?php include_once("nav.php"); ?>
</nav>

<main>

<!-- HEADER -->
<header>
    <div>
        <h1>Gestion de documents</h1>
        <p><?= $totalDocs ?> document<?= $totalDocs > 1 ? 's' : '' ?> au total</p>
    </div>
    <div class="header-badges">
        <span class="hbadge hbadge-pending">
            <i class="fa-solid fa-hourglass-half" style="font-size:10px;"></i>
            <?= $totalPending ?> en attente
        </span>
        <span class="hbadge hbadge-validated">
            <i class="fa-solid fa-circle-check" style="font-size:10px;"></i>
            <?= $totalValidated ?> validés
        </span>
        <span class="hbadge hbadge-rejected">
            <i class="fa-solid fa-circle-xmark" style="font-size:10px;"></i>
            <?= $totalRejected ?> rejetés
        </span>
    </div>
</header>

<!-- WIDGETS (FILTRES) -->
<div id="widgets">
<form method="GET">

<input type="text" name="search" placeholder="Rechercher un document ou utilisateur..."
value="<?= htmlspecialchars($search) ?>">

<select name="filter">
<option value="all"       <?= $filter=='all'       ?'selected':'' ?>>Tous les statuts</option>
<option value="pending"   <?= $filter=='pending'   ?'selected':'' ?>>En attente</option>
<option value="validated" <?= $filter=='validated' ?'selected':'' ?>>Validés</option>
<option value="rejected"  <?= $filter=='rejected'  ?'selected':'' ?>>Rejetés</option>
</select>

<select name="tri">
<option value="a-z"    <?= $tri=='a-z'    ?'selected':'' ?>>Nom A → Z</option>
<option value="z-a"    <?= $tri=='z-a'    ?'selected':'' ?>>Nom Z → A</option>
<option value="newer"  <?= $tri=='newer'  ?'selected':'' ?>>Plus récents</option>
<option value="older"  <?= $tri=='older'  ?'selected':'' ?>>Plus anciens</option>
</select>

<button type="submit">
    <i class="fa-solid fa-filter"></i>
    Filtrer
</button>

<?php if (!empty($search) || $filter !== 'all'): ?>
<a href="documents.php" style="font-size:13px; color:var(--ink-muted); align-self:center;">
    <i class="fa-solid fa-xmark"></i> Réinitialiser
</a>
<?php endif; ?>

</form>
</div>

<!-- TABLE -->
<div class="table-container">
<table>

<thead>
<tr>
<th>Utilisateur</th>
<th>Document</th>
<th>Date</th>
<th>Statut</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php if (count($documents) === 0): ?>
<tr class="empty-row">
<td colspan="5">
    <i class="fa-regular fa-folder-open"></i>
    Aucun document trouvé
</td>
</tr>
<?php endif; ?>

<?php foreach ($documents as $doc): ?>
<tr>
<td><?= htmlspecialchars($doc['user_name']) ?></td>
<td>
    <div class="file-cell">
        <i class="fa-regular fa-file-lines"></i>
        <?= htmlspecialchars($doc['file_name']) ?>
    </div>
</td>
<td style="color:var(--ink-muted); font-size:13px;">
    <?= date('d/m/Y', strtotime($doc['uploaded_at'])) ?>
</td>
<td>
    <?php
    $statusMap = [
        'validated' => ['label' => 'Validé',     'class' => 'badge-validated'],
        'rejected'  => ['label' => 'Rejeté',     'class' => 'badge-rejected'],
        'pending'   => ['label' => 'En attente', 'class' => 'badge-pending'],
        'new'       => ['label' => 'Nouveau',    'class' => 'badge-new'],
    ];
    $s = $statusMap[$doc['status']] ?? ['label' => ucfirst($doc['status']), 'class' => 'badge-new'];
    ?>
    <span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span>
</td>
<td>
    <?php if ($doc['status'] === 'pending' || $doc['status'] === 'new'): ?>
        <a href="view-document.php?id=<?= $doc['id'] ?>" class="btn-action btn-treat">
            <i class="fa-solid fa-eye"></i> Traiter
        </a>
    <?php else: ?>
        <a href="view-document.php?id=<?= $doc['id'] ?>" class="btn-action btn-view">
            <i class="fa-regular fa-eye"></i> Voir
        </a>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>

</tbody>

</table>

<!-- PAGINATION -->
<div class="pagination-bar">
    <span>
        <?= $totalDocs ?> résultat<?= $totalDocs > 1 ? 's' : '' ?>
        — page <?= $page ?> sur <?= $totalPages ?>
    </span>
    <div class="pagination">
        <a href="?page=<?= max(1,$page-1) ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>&tri=<?= $tri ?>"
           class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
            <i class="fa-solid fa-chevron-left" style="font-size:11px;"></i>
        </a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>&tri=<?= $tri ?>"
               class="page-btn <?= $i === $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPages,$page+1) ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>&tri=<?= $tri ?>"
           class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <i class="fa-solid fa-chevron-right" style="font-size:11px;"></i>
        </a>
    </div>
</div>

</div>

</main>

</body>
</html>