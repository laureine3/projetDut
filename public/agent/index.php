<?php require_once 'partials/header.php'; ?>
<?php require_once 'partials/sidebar.php'; ?>
<?php require_once '../../config/database.php'; ?>

<h2 class="mb-4">Dashboard Agent</h2>

<?php
$pending = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='pending'")->fetchColumn();
$validated = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='validated'")->fetchColumn();
$rejected = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='rejected'")->fetchColumn();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card p-4 text-center">
            <h5>En attente</h5>
            <h2 class="text-warning"><?= $pending ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 text-center">
            <h5>Validés</h5>
            <h2 class="text-success"><?= $validated ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 text-center">
            <h5>Rejetés</h5>
            <h2 class="text-danger"><?= $rejected ?></h2>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>