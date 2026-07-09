<?php
session_start();

require_once "../../config/database.php";
require_once "../../config/security.php";

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
exit("Accès refusé");
}

$user_id = intval($_GET['user_id'] ?? 0);

$stmt = $pdo->prepare("SELECT name,role FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if(!$user){
exit("Utilisateur introuvable");
}

$total_validated = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE validated_by=?");
$total_validated->execute([$user_id]);
$total_v = $total_validated->fetchColumn();

$total_rejected = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE rejected_by=?");
$total_rejected->execute([$user_id]);
$total_r = $total_rejected->fetchColumn();

$total_docs = $total_v + $total_r;

$validated = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE validated_by=? AND status='validated'");
$validated->execute([$user_id]);
$validated_docs = $validated->fetchColumn();

$rejected = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE rejected_by=? AND status='rejected'");
$rejected->execute([$user_id]);
$rejected_docs = $rejected->fetchColumn();

// $pending = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE processed_by=? AND status='pending'");
// $pending->execute([$user_id]);
// $pending_docs = $pending->fetchColumn();

?>

<!DOCTYPE html>
<html lang="fr">
<head>

<meta charset="UTF-8">
<title>Activité utilisateur</title>

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
  padding: 30px;
  color: #1f2937;
}

/* TITRE */
h2 {
  font-size: 24px;
  margin-bottom: 20px;
  color: #111827;
}

/* STATS CONTAINER */
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
}

/* CARDS */
.card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.08);
  text-align: center;
  transition: 0.3s ease;
  border-left: 5px solid #3b82f6;
}

/* HOVER EFFECT */
.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.12);
}

/* VALUES (numbers) */
.card b {
  font-size: 14px;
  color: #6b7280;
}

.card br + * {
  font-size: 28px;
  font-weight: bold;
  color: #111827;
}

/* LINK */
a {
  display: inline-block;
  margin-top: 25px;
  text-decoration: none;
  background: #3b82f6;
  color: white;
  padding: 10px 15px;
  border-radius: 8px;
  transition: 0.3s;
}

a:hover {
  background: #2563eb;
  transform: translateY(-2px);
}

</style>

</head>

<body>

<h2>Activité de <?= htmlspecialchars($user['name']) ?></h2>

<div class="stats">

<div class="card">
<b>Total documents traités</b>
<br>
<?= $total_docs ?>
</div>

<div class="card">
<b>Documents validés</b>
<br>
<?= $validated_docs ?>
</div>

<div class="card">
<b>Documents rejetés</b>
<br>
<?= $rejected_docs ?>
</div>

</div>

<br><br>

<a href="users.php">Retour</a>

</body>
</html>