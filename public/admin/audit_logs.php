<?php
session_start();

require_once "../../config/database.php";
require_once "../../config/security.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit("Accès refusé");
}

$operators = $pdo->query("SELECT id,name FROM users ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit système — GestiDoc</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

    /* ── Variables ── */
    :root {
        --primary:     #2563eb;
        --sidebar-bg:  #1e293b;
        --sidebar-text:#94a3b8;
        --bg-body:     #f5f7fa;
        --text-main:   #0d1b2a;
        --text-muted:  #7f8fa4;
        --white:       #ffffff;
        --danger:      #dc2626;
        --success:     #059669;
        --warning:     #d97706;
        --ink:         #0d1b2a;
        --ink-muted:   #7f8fa4;
        --line:        #e8ecf1;
        --surface:     #ffffff;
        --bg:          #f5f7fa;
        --blue:        #2563eb;
        --blue-soft:   #eff4ff;
        --blue-dark:   #1d4ed8;
        --green:       #059669;
        --green-soft:  #ecfdf5;
        --amber:       #d97706;
        --amber-soft:  #fffbeb;
        --red:         #dc2626;
        --red-soft:    #fef2f2;
        --purple:      #7c3aed;
        --purple-soft: rgba(124,58,237,.1);
        --shadow:      0 4px 16px rgba(13,27,42,.08);
        --r:           10px;
    }

    /* RESET */
    *, *::before, *::after {
        margin: 0; padding: 0;
        box-sizing: border-box;
        font-family: 'DM Sans', 'Segoe UI', sans-serif;
    }

    /* BODY */
    body {
        padding: 28px;
        background: var(--bg);
        color: var(--ink);
        padding: 28px;
        margin: 0;
        font-family: 'DM Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--bg-body);
        color: var(--text-main);
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        gap: 18px;
    }

    /* SIDEBAR SPACE */
    body {
        margin-left: 220px;
    }

    /* ── Header ── */
    .header {
        background: var(--surface);
        padding: 16px 22px;
        border-radius: var(--r);
        border: 1px solid var(--line);
        box-shadow: var(--shadow);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* TITLE */
    h2 {
        font-family: 'DM Serif Display', serif;
        font-size: 20px;
        font-weight: 400;
        color: var(--ink);
        margin-bottom: 0;
    }

    .header-sub {
        font-size: 13px;
        color: var(--ink-muted);
        margin-top: 2px;
    }

    /* ── Badge header ── */
    .header-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        background: var(--blue-soft);
        color: var(--blue);
        font-size: 12px; font-weight: 500;
    }

    /* SELECT + INPUT */
    select,
    input[type="date"] {
        padding: 9px 12px;
        border-radius: 8px;
        border: 1.5px solid var(--line);
        background: var(--bg);
        outline: none;
        font-size: 13.5px;
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
        transition: .2s;
    }

    select:focus,
    input[type="date"]:focus {
        border-color: var(--blue);
        background: var(--surface);
        box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }

    /* BUTTON */
    button {
        background: var(--blue);
        color: white;
        border: none;
        padding: 9px 18px;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.2s;
        font-size: 13.5px;
        font-family: 'DM Sans', sans-serif;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    button:hover {
        background: var(--blue-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37,99,235,.25);
    }

    /* ── ACTIONS (filtres) ── */
    .actions {
        padding: 0;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--r);
        box-shadow: var(--shadow);
        padding: 14px 18px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }

    .actions label {
        font-size: 12px;
        font-weight: 600;
        color: var(--ink-muted);
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    /* ── Table container ── */
    .table {
        background: var(--surface);
        border-radius: var(--r);
        overflow: hidden;
        border: 1px solid var(--line);
        box-shadow: var(--shadow);
    }

    /* TABLE */
    table {
        padding: 0;
        width: 100%;
        border-collapse: collapse;
        background: var(--surface);
        border-radius: 0;
        overflow: hidden;
        box-shadow: none;
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

    tbody tr:last-child td { border-bottom: none; }

    tr:hover { background: #fafbfd; transition: .15s; }

    /* BADGES ACTIONS */
    td:nth-child(3) {
        font-weight: bold;
        color: var(--blue);
    }

    /* ── Badge action coloré ── */
    .action-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px;
        border-radius: 5px;
        font-size: 11px;
        font-weight: 600;
        font-family: monospace;
        letter-spacing: .3px;
    }

    .action-CREATE_AGENT    { background: var(--green-soft);  color: var(--green); }
    .action-RESET_PASSWORD  { background: var(--amber-soft);  color: var(--amber); }
    .action-TOGGLE_STATUS   { background: var(--blue-soft);   color: var(--blue); }
    .action-PROMOTE_ADMIN   { background: var(--purple-soft); color: var(--purple); }
    .action-DEMOTE_ADMIN    { background: var(--red-soft);    color: var(--red); }
    .action-UPLOAD          { background: var(--blue-soft);   color: var(--blue); }
    .action-VALIDATION      { background: var(--green-soft);  color: var(--green); }
    .action-REJECTION       { background: var(--red-soft);    color: var(--red); }
    .action-default         { background: var(--bg);          color: var(--ink-muted); }

    /* ── Cible ── */
    .target-chip {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 12px; color: var(--ink-muted);
        background: var(--bg);
        border: 1px solid var(--line);
        padding: 2px 8px; border-radius: 5px;
        font-family: monospace;
    }

    /* Vide */
    .empty-row td {
        text-align: center;
        padding: 40px;
        color: var(--ink-muted);
        font-size: 14px;
    }

    .empty-row i {
        display: block; font-size: 32px;
        color: var(--line); margin-bottom: 10px;
    }

    /* PAGINATION */
    #pagination {
        margin-top: 0;
        display: flex;
        gap: 6px;
        padding: 12px 16px;
        border-top: 1px solid var(--line);
        align-items: center;
    }

    #pagination button {
        background: var(--surface);
        color: var(--ink-muted);
        border: 1px solid var(--line);
        width: 30px; height: 30px;
        padding: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 500;
        box-shadow: none;
    }

    #pagination button:hover {
        border-color: var(--blue);
        color: var(--blue);
        transform: none;
        box-shadow: none;
    }

    #pagination button.active {
        background: var(--blue);
        color: white;
        border: none;
    }

    </style>
</head>

<body>

    <?php
    $active_page = "audits";
    include_once("nav.php");
    ?>

    <!-- HEADER -->
    <div class="header">
        <div>
            <h2>Audit système</h2>
            <div class="header-sub">Historique de toutes les actions effectuées</div>
        </div>
        <div class="header-badge">
            <i class="fa-solid fa-scroll" style="font-size:12px;"></i>
            Journal complet
        </div>
    </div>

    <!-- FILTRES -->
    <div class="actions">

        <label>Opérateur :</label>
        <select id="operator">
            <option value="">Tous</option>
            <?php foreach ($operators as $op): ?>
                <option value="<?= $op['id'] ?>">
                    <?= htmlspecialchars($op['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Action :</label>
        <select id="action">
            <option value="">Toutes</option>
            <option value="CREATE_AGENT">CREATE_AGENT</option>
            <option value="RESET_PASSWORD">RESET_PASSWORD</option>
            <option value="TOGGLE_STATUS">TOGGLE_STATUS</option>
            <option value="PROMOTE_ADMIN">PROMOTE_ADMIN</option>
            <option value="DEMOTE_ADMIN">DEMOTE_ADMIN</option>
            <option value="UPLOAD">UPLOAD</option>
            <option value="VALIDATION">VALIDATION</option>
            <option value="REJECTION">REJECTION</option>
        </select>

        <label>Date début :</label>
        <input type="date" id="date_start">

        <label>Date fin :</label>
        <input type="date" id="date_end">

        <button onclick="loadLogs()">
            <i class="fa-solid fa-filter"></i>
            Filtrer
        </button>

        <button onclick="resetFilters()" style="background:var(--bg); color:var(--ink-muted); border:1px solid var(--line); box-shadow:none;">
            <i class="fa-solid fa-xmark"></i>
            Réinitialiser
        </button>

    </div>

    <!-- TABLE -->
    <div class="table">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Opérateur</th>
                    <th>Action</th>
                    <th>Cible</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody id="logsTable">
                <tr class="empty-row">
                    <td colspan="5">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        Chargement...
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- PAGINATION -->
        <div id="pagination"></div>
    </div>


    <script>
        let page = 1;

        /* ── Couleur badge action ── */
        const actionColors = {
            'CREATE_AGENT':   'action-CREATE_AGENT',
            'RESET_PASSWORD': 'action-RESET_PASSWORD',
            'TOGGLE_STATUS':  'action-TOGGLE_STATUS',
            'PROMOTE_ADMIN':  'action-PROMOTE_ADMIN',
            'DEMOTE_ADMIN':   'action-DEMOTE_ADMIN',
            'UPLOAD':         'action-UPLOAD',
            'VALIDATION':     'action-VALIDATION',
            'REJECTION':      'action-REJECTION',
        };

        function loadLogs() {

            let operator = document.getElementById("operator").value;
            let action   = document.getElementById("action").value;
            let start    = document.getElementById("date_start").value;
            let end      = document.getElementById("date_end").value;

            /* Indicateur chargement */
            document.getElementById("logsTable").innerHTML = `
                <tr class="empty-row">
                    <td colspan="5">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        Chargement...
                    </td>
                </tr>`;

            fetch("audit_logs_data.php?page=" + page +
                    "&operator=" + operator +
                    "&action="   + action   +
                    "&start="    + start    +
                    "&end="      + end)

                .then(res => res.json())

                .then(data => {

                    let table = document.getElementById("logsTable");
                    table.innerHTML = "";

                    if (!data.logs || data.logs.length === 0) {
                        table.innerHTML = `
                            <tr class="empty-row">
                                <td colspan="5">
                                    <i class="fa-regular fa-folder-open"></i>
                                    Aucun log trouvé
                                </td>
                            </tr>`;
                        document.getElementById("pagination").innerHTML = "";
                        return;
                    }

                    data.logs.forEach(log => {

                        const cls = actionColors[log.action_type] || 'action-default';

                        table.innerHTML += `
<tr>
<td style="color:var(--ink-muted); font-size:13px;">
    <i class="fa-regular fa-clock" style="margin-right:5px;"></i>
    ${log.logged_at}
</td>
<td style="font-weight:500;">${log.operator}</td>
<td>
    <span class="action-badge ${cls}">${log.action_type}</span>
</td>
<td>
    <span class="target-chip">${log.target_type} #${log.target_id}</span>
</td>
<td style="color:var(--ink-muted);">${log.description}</td>
</tr>`;

                    });

                    /* Pagination */
                    let pagination = document.getElementById("pagination");
                    pagination.innerHTML = "";

                    if (data.pages > 1) {
                        pagination.innerHTML += `<button onclick="goPage(${Math.max(1,page-1)})" ${page<=1?'disabled':''}>
                            <i class="fa-solid fa-chevron-left" style="font-size:10px;"></i>
                        </button>`;

                        for (let i = 1; i <= data.pages; i++) {
                            pagination.innerHTML += `
                                <button onclick="goPage(${i})" class="${i===page?'active':''}">${i}</button>`;
                        }

                        pagination.innerHTML += `<button onclick="goPage(${Math.min(data.pages,page+1)})" ${page>=data.pages?'disabled':''}>
                            <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
                        </button>`;
                    }

                });
        }

        function goPage(p) {
            page = p;
            loadLogs();
        }

        function resetFilters() {
            document.getElementById("operator").value   = "";
            document.getElementById("action").value     = "";
            document.getElementById("date_start").value = "";
            document.getElementById("date_end").value   = "";
            page = 1;
            loadLogs();
        }

        loadLogs();
    </script>

</body>
</html>