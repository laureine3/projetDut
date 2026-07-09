<?php
require_once "../../config/database.php";
session_start();

if (!isset($_GET['id'])) {
    die("Document introuvable.");
}

$document_id = intval($_GET['id']);

/* =====================================================
   RÉCUPÉRATION DOCUMENT
===================================================== */
$stmt = $pdo->prepare("
    SELECT d.*, 
           u.name AS uploader_name,
           s.name AS detected_service_name,
           c.name AS detected_category_name,
           v.name AS validator_name,
           r.name AS rejector_name
    FROM documents d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN services s ON d.detected_service_id = s.id
    LEFT JOIN document_categories c ON d.detected_category_id = c.id
    LEFT JOIN users v ON d.validated_by = v.id
    LEFT JOIN users r ON d.rejected_by = r.id
    WHERE d.id = ?
");
$stmt->execute([$document_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die("Document non trouvé.");
}

$file_name = $document['file_name'];
$current_relative_path = $document['file_path'];
$current_full_path = "../../" . $current_relative_path;
$status = $document['status'];

/* =====================================================
   LISTE SERVICES & CATÉGORIES
===================================================== */
$services = $pdo->query("SELECT id, name FROM services")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT id, name FROM document_categories")->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   BLOQUER SI DÉJÀ TRAITÉ
===================================================== */
$is_read_only = ($status === 'validated' || $status === 'rejected');

/* =====================================================
   TRAITEMENT FORMULAIRE (SEULEMENT SI PENDING)
===================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_read_only) {

    $operator_id   = $_SESSION['user_id'] ?? null;
    $operator_role = $_SESSION['role'] ?? 'agent';
    $operator_name = $_SESSION['name'] ?? 'Opérateur';

    $final_service = !empty($_POST['service']) 
        ? intval($_POST['service']) 
        : $document['detected_service_id'];

    $final_category = !empty($_POST['category']) 
        ? intval($_POST['category']) 
        : $document['detected_category_id'];

    /* ================= VALIDATION ================= */
    if (isset($_POST['validate'])) {

        $new_relative_path = str_replace("pending/", "validated/", $current_relative_path);
        rename($current_full_path, "../../" . $new_relative_path);

        $update = $pdo->prepare("
            UPDATE documents
            SET status = 'validated',
                file_path = ?,
                detected_service_id = ?,
                detected_category_id = ?,
                validated_by = ?,
                validated_at = NOW()
            WHERE id = ?
        ");
        $update->execute([
            $new_relative_path,
            $final_service,
            $final_category,
            $operator_id,
            $document_id
        ]);

        $description = sprintf(
            "%s (%s) a validé le document %s",
            $operator_name,
            strtoupper($operator_role),
            $file_name
        );

        $log = $pdo->prepare("
            INSERT INTO audit_logs
            (operator_id, operator_role, action_type, target_type, target_id, description)
            VALUES (?, ?, 'VALIDATION', 'DOCUMENT', ?, ?)
        ");
        $log->execute([$operator_id, $operator_role, $document_id, $description]);

        header("Location: view-document.php?id=" . $document_id);
        exit();
    }

    /* ================= REJET ================= */
    if (isset($_POST['reject'])) {

        $reason = trim($_POST['rejection_reason']);

        if (empty($reason)) {
            $error = "La raison du rejet est obligatoire.";
        } else {

            $new_relative_path = str_replace("pending/", "rejected/", $current_relative_path);
            rename($current_full_path, "../../" . $new_relative_path);

            $update = $pdo->prepare("
                UPDATE documents
                SET status = 'rejected',
                    file_path = ?,
                    rejection_reason = ?,
                    rejected_by = ?,
                    rejected_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$new_relative_path, $reason, $operator_id, $document_id]);

            $description = sprintf(
                "%s (%s) a rejeté le document %s pour la raison suivante : %s",
                $operator_name,
                strtoupper($operator_role),
                $file_name,
                $reason
            );

            $log = $pdo->prepare("
                INSERT INTO audit_logs
                (operator_id, operator_role, action_type, target_type, target_id, description)
                VALUES (?, ?, 'REJECTION', 'DOCUMENT', ?, ?)
            ");
            $log->execute([$operator_id, $operator_role, $document_id, $description]);

            header("Location: view-document.php?id=" . $document_id);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Consultation document</title>
<style>
    /* RESET */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Segoe UI', sans-serif;
}

/* BODY */
body {
  background: #f4f6f9;
  color: #1f2937;
  padding: 20px;
}

/* NAV */
nav {
  margin-bottom: 10px;
}

/* HEADER */
header {
  background: white;
  padding: 15px 20px;
  border-radius: 12px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.05);
  margin-bottom: 20px;

  display: flex;
  align-items: center;
  gap: 12px;
}


header h1 {
  font-size: 22px;
}

/* TITRE FICHIER */
h3 {
  font-size: 18px;
  margin-bottom: 10px;
  color: #111827;
}

/* INFOS DOCUMENT */
p {
  margin: 5px 0;
  font-size: 14px;
}

/* STRONG LABELS */
p strong {
  color: #374151;
}

/* STATUT GLOBAL */
p:nth-of-type(3) strong {
  color: #3b82f6;
}

/* CONTAINER FILE */
div[style*="width: 800px"] {
  background: white;
  padding: 15px;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.05);
  margin: 20px 0;
}

/* PDF FRAME */
iframe {
  border: none;
  border-radius: 10px;
}

/* IMAGE */
img {
  border-radius: 10px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* FORM */
form {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.05);
  margin-top: 20px;
}

/* SELECT */
select {
  width: 100%;
  padding: 10px;
  margin: 8px 0;
  border-radius: 8px;
  border: 1px solid #ddd;
  outline: none;
}

/* TEXTAREA */
textarea {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ddd;
  resize: none;
}


/* Bouton "Retour" (style identique à l’admin dashboard) */
.btn {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    background: var(--blue, #2563eb);
    color: #ffffff;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(37,99,235,.25);
    transition: .2s;
    margin-left: auto; /* pousse à droite */
}

.btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}



/* BUTTONS */
button {
  padding: 10px 15px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  margin-top: 10px;
  transition: 0.3s;
  font-size: 14px;
}

/* PRIMARY BUTTON (VALIDER) */
button[name="validate"] {
  background: #10b981;
  color: white;
}

button[name="validate"]:hover {
  background: #059669;
}

/* REJECT BUTTON */
button[onclick="toggleReject()"] {
  background: #ef4444;
  color: white;
}

button[onclick="toggleReject()"]:hover {
  background: #dc2626;
}

/* CONFIRM REJECT */
button[name="reject"] {
  background: #b91c1c;
  color: white;
}

/* EDIT BUTTON */
button[onclick="toggleEdit()"] {
  background: #3b82f6;
  color: white;
}

button[onclick="toggleEdit()"]:hover {
  background: #2563eb;
}

/* EDIT + REJECT SECTIONS */
#editSection,
#rejectSection {
  margin-top: 15px;
  padding: 10px;
  background: #f9fafb;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
}

/* ERROR */
p[style*="color:red"] {
  background: #fee2e2;
  color: #b91c1c;
  padding: 10px;
  border-radius: 8px;
  font-weight: bold;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  body {
    padding: 10px;
  }

  div[style*="width: 800px"] {
    width: 100% !important;
  }
}
</style>
</head>

<body>



<header>
<h1>Consultation du document</h1>

<a href="documents.php" class="btn">
        <i class="fas fa-arrow-left"></i>
        Retour
    </a>
</header>

 

<h3><?= htmlspecialchars($file_name) ?></h3>

<p><strong>Uploader :</strong> <?= htmlspecialchars($document['uploader_name']) ?></p>
<p><strong>Date upload :</strong> <?= htmlspecialchars($document['uploaded_at']) ?></p>

<p><strong>Statut :</strong> <?= strtoupper($status) ?></p>
<p><strong>Niveau confiance :</strong> <?= htmlspecialchars($document['confidence_level']) ?></p>

<?php if ($status === 'validated'): ?>
    <p><strong>Validé par :</strong> <?= htmlspecialchars($document['validator_name']) ?></p>
    <p><strong>Date validation :</strong> <?= htmlspecialchars($document['validated_at']) ?></p>
<?php endif; ?>

<?php if ($status === 'rejected'): ?>
    <p><strong>Rejeté par :</strong> <?= htmlspecialchars($document['rejector_name']) ?></p>
    <p><strong>Raison rejet :</strong> <?= htmlspecialchars($document['rejection_reason']) ?></p>
<?php endif; ?>

<hr>

<?php
$extension = strtolower(pathinfo($current_relative_path, PATHINFO_EXTENSION));
?>

<div style="width: 800px; max-width: 100%;">

<?php if ($extension === 'pdf'): ?>

    <iframe 
        src="../../<?= htmlspecialchars($current_relative_path) ?>" 
        width="100%" 
        height="800px">
    </iframe>

<?php else: ?>

    <img 
        src="../../<?= htmlspecialchars($current_relative_path) ?>" 
        style="max-width: 100%; height: auto; display: block;"
        alt="Document image">

<?php endif; ?>

</div>

<hr>

<?php if (!$is_read_only): ?>

<?php if (isset($error)) : ?>
<p style="color:red"><?= $error ?></p>
<?php endif; ?>

<form method="post">

<h3>Détection OCR</h3>

<p><strong>Service détecté :</strong>
<?= htmlspecialchars($document['detected_service_name'] ?? "Non détecté") ?>
</p>

<p><strong>Catégorie détectée :</strong>
<?= htmlspecialchars($document['detected_category_name'] ?? "Non détectée") ?>
</p>

<button type="button" onclick="toggleEdit()">Modifier</button>

<div id="editSection" style="display:none; margin-top:15px;">

<select name="service">
<option value="">-- Choisir service --</option>
<?php foreach ($services as $service): ?>
<option value="<?= $service['id'] ?>">
<?= htmlspecialchars($service['name']) ?>
</option>
<?php endforeach; ?>
</select>

<select name="category">
<option value="">-- Choisir catégorie --</option>
<?php foreach ($categories as $category): ?>
<option value="<?= $category['id'] ?>">
<?= htmlspecialchars($category['name']) ?>
</option>
<?php endforeach; ?>
</select>

</div>

<hr>

<button type="submit" name="validate">Valider</button>
<button type="button" onclick="toggleReject()">Rejeter</button>

<div id="rejectSection" style="display:none; margin-top:10px;">
<textarea name="rejection_reason"
placeholder="Indiquer la raison du rejet..."
style="width:300px;height:80px;"></textarea>
<br>
<button type="submit" name="reject">Confirmer rejet</button>
</div>

</form>

<?php endif; ?>

<script>
function toggleEdit() {
document.getElementById("editSection").style.display = "block";
}
function toggleReject() {
document.getElementById("rejectSection").style.display = "block";
}
</script>

</body>
</html>