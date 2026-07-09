<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/auth.php";

requireRole('admin');

/* ── Récupération notifications ── */
$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE (user_id = ? OR user_id IS NULL)
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Marquer comme lues ── */
$update = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE (user_id = ? OR user_id IS NULL)
");
$update->execute([$_SESSION['user_id']]);

/* ── Compteurs ── */
$totalNotifs  = count($notifications);
$unreadNotifs = count(array_filter($notifications, fn($n) => !$n['is_read']));

$typeIcons = [
    'success' => ['icon' => 'fa-circle-check',    'class' => 'type-success'],
    'error'   => ['icon' => 'fa-circle-xmark',    'class' => 'type-error'],
    'info'    => ['icon' => 'fa-circle-info',      'class' => 'type-info'],
    'warning' => ['icon' => 'fa-triangle-exclamation', 'class' => 'type-warning'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — GestiDoc</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>

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

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', Arial, sans-serif;
    background: var(--bg);
    color: var(--ink);
    display: flex;
    min-height: 100vh;
}

.container {
    margin-left: 220px;
    padding: 28px;
    flex: 1;
}

/* ── Header ── */
.page-header {
    background: var(--surface);
    padding: 16px 22px;
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 20px;
    font-weight: 400;
    color: var(--ink);
}

.page-header p {
    font-size: 13px;
    color: var(--ink-muted);
    margin-top: 2px;
}

/* ── Badges header ── */
.header-badges { display: flex; gap: 8px; align-items: center; }

.hbadge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 500;
}

.hbadge-total  { background: var(--blue-soft); color: var(--blue); }
.hbadge-unread { background: var(--amber-soft); color: var(--amber); }

/* ── Filtres ── */
.filters-bar {
    background: var(--surface);
    padding: 12px 16px;
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
    display: flex; gap: 8px; flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
}

.filter-btn {
    padding: 6px 14px;
    border-radius: 7px;
    border: 1.5px solid var(--line);
    background: var(--bg);
    color: var(--ink-muted);
    font-size: 13px; font-weight: 500;
    cursor: pointer; transition: .2s;
    font-family: 'DM Sans', Arial, sans-serif;
    display: inline-flex; align-items: center; gap: 6px;
}

.filter-btn:hover,
.filter-btn.active {
    border-color: var(--blue);
    color: var(--blue);
    background: var(--blue-soft);
}

/* ── Bouton supprimer tout ── */
.btn-delete-all {
    margin-left: auto;
    padding: 6px 14px;
    border-radius: 7px;
    border: 1.5px solid rgba(220,38,38,.2);
    background: var(--red-soft);
    color: var(--red);
    font-size: 13px; font-weight: 500;
    cursor: pointer; transition: .2s;
    font-family: 'DM Sans', Arial, sans-serif;
    display: inline-flex; align-items: center; gap: 6px;
}

.btn-delete-all:hover { background: #fee2e2; }

/* ── Notifications list ── */
.notif-list { display: flex; flex-direction: column; gap: 10px; }

/* ── Card ── */
.card {
    background: var(--surface);
    padding: 16px 20px;
    border-radius: var(--r);
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
    display: flex;
    gap: 14px;
    align-items: flex-start;
    transition: .2s;
    position: relative;
}

.card:hover { box-shadow: 0 6px 20px rgba(13,27,42,.1); }

/* Ligne gauche colorée si non lu */
.unread {
    border-left: 3px solid var(--blue);
}

/* ── Icône type ── */
.notif-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}

.type-success { background: var(--green-soft); color: var(--green); }
.type-error   { background: var(--red-soft);   color: var(--red); }
.type-info    { background: var(--blue-soft);  color: var(--blue); }
.type-warning { background: var(--amber-soft); color: var(--amber); }

/* ── Contenu ── */
.notif-body { flex: 1; }

.notif-body h3 {
    font-size: 14px; font-weight: 600;
    color: var(--ink); margin-bottom: 4px;
}

.notif-body p {
    font-size: 13.5px;
    color: var(--ink-muted);
    line-height: 1.6;
    margin-bottom: 6px;
}

.notif-body time {
    font-size: 11.5px;
    color: var(--ink-muted);
    display: flex; align-items: center; gap: 5px;
}

/* ── Badge non lu ── */
.unread-dot {
    width: 8px; height: 8px;
    background: var(--blue);
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 6px;
}

/* ── Vide ── */
.empty-state {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--r);
    padding: 60px 20px;
    text-align: center;
}

.empty-state i {
    font-size: 40px;
    color: var(--line);
    margin-bottom: 14px;
    display: block;
}

.empty-state p {
    font-size: 14px;
    color: var(--ink-muted);
}

</style>
</head>
<body>

<?php
$active_page = "notifications";
include_once("nav.php");
?>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>Notifications</h1>
            <p>Historique de toutes vos notifications</p>
        </div>
        <div class="header-badges">
            <span class="hbadge hbadge-total">
                <i class="fa-solid fa-bell" style="font-size:10px;"></i>
                <?= $totalNotifs ?> au total
            </span>
            <?php if ($unreadNotifs > 0): ?>
            <span class="hbadge hbadge-unread">
                <i class="fa-solid fa-circle" style="font-size:8px;"></i>
                <?= $unreadNotifs ?> non lues
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtres JS -->
    <div class="filters-bar">
        <button class="filter-btn active" onclick="filterNotifs('all', this)">
            <i class="fa-solid fa-list"></i> Toutes
        </button>
        <button class="filter-btn" onclick="filterNotifs('success', this)">
            <i class="fa-solid fa-circle-check"></i> Validations
        </button>
        <button class="filter-btn" onclick="filterNotifs('error', this)">
            <i class="fa-solid fa-circle-xmark"></i> Rejets
        </button>
        <button class="filter-btn" onclick="filterNotifs('info', this)">
            <i class="fa-solid fa-circle-info"></i> Infos
        </button>
        <button class="filter-btn" onclick="filterNotifs('warning', this)">
            <i class="fa-solid fa-triangle-exclamation"></i> Alertes
        </button>
        <button class="btn-delete-all" onclick="if(confirm('Supprimer toutes les notifications ?')) window.location='?clear=1'">
            <i class="fa-solid fa-trash"></i> Tout supprimer
        </button>
    </div>

    <!-- Liste -->
    <div class="notif-list" id="notifList">

        <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-bell-slash"></i>
            <p>Aucune notification pour le moment.</p>
        </div>
        <?php else: ?>

        <?php foreach ($notifications as $notif):
            $type = $notif['type'] ?? 'info';
            $ti   = $typeIcons[$type] ?? $typeIcons['info'];
            $isUnread = !$notif['is_read'];
        ?>
        <div class="card <?= $isUnread ? 'unread' : '' ?>" data-type="<?= htmlspecialchars($type) ?>">

            <div class="notif-icon <?= $ti['class'] ?>">
                <i class="fa-solid <?= $ti['icon'] ?>"></i>
            </div>

            <div class="notif-body">
                <h3><?= htmlspecialchars($notif['title']) ?></h3>
                <p><?= htmlspecialchars($notif['message']) ?></p>
                <time>
                    <i class="fa-regular fa-clock"></i>
                    <?= date('d/m/Y à H:i', strtotime($notif['created_at'])) ?>
                </time>
            </div>

            <?php if ($isUnread): ?>
                <div class="unread-dot" title="Non lue"></div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

<?php
/* ── Suppression si ?clear=1 ── */
if (isset($_GET['clear'])) {
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ? OR user_id IS NULL")
        ->execute([$_SESSION['user_id']]);
    header("Location: notifications.php");
    exit;
}
?>

<script>
function filterNotifs(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.card').forEach(card => {
        card.style.display = (type === 'all' || card.dataset.type === type) ? 'flex' : 'none';
    });
}
</script>

</body>
</html>